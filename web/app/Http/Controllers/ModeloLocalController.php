<?php

namespace App\Http\Controllers;

use App\Services\CaseAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ModeloLocalController extends Controller
{
    public function getModels()
    {
        $apiUrl = env('AI_API_URL', 'http://127.0.0.1:1234/v1/chat/completions');

        $modelsUrl = str_replace('/chat/completions', '/models', $apiUrl);
        $token = env('NVIDIA_API_KEY', 'local-token');

        $response = Http::withToken($token)->get($modelsUrl);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'Failed to fetch models'], 500);
    }

    public function analyze(Request $request)
    {
        set_time_limit(0);

        $step = $request->input('step');
        $relato = $request->input('relato');
        $context = $request->input('context');
        $requestedModel = $request->input('model');
        $prompt = $this->getPrompt($step, $relato, $context);

        $apiUrl = env('AI_API_URL', 'http://127.0.0.1:1234/v1/chat/completions');
        $model = $requestedModel ?: env('AI_MODEL', 'qwen/qwen2.5-coder-7b-instruct');
        $token = env('NVIDIA_API_KEY', 'local-token');

        $response = Http::withToken($token)
            ->timeout(0)
            ->post($apiUrl, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Responde ÚNICAMENTE con un Array JSON válido. No uses bloques de código markdown (```json). No agregues explicaciones, saludos ni conclusiones. Solo el JSON puro. Para tipo_nodo usa EXCLUSIVAMENTE uno de estos valores exactos: permanente, hecho, hipotesis o lesion. Usa hipotesis solo para una inferencia causal razonable basada en dos o más hechos del relato; no la presentes como hecho confirmado. Nunca uses exclusiva, causa, condicion, actividad, exposicion, repeticion, efecto o evolucion como tipo_nodo. Para tipo_relacion usa EXCLUSIVAMENTE uno de estos valores exactos: cadena, conjuncion o disyuncion. Nunca uses nombres de nodos, etapas o conceptos como condicion, actividad, exposicion, repeticion, efecto, evolucion o lesion. origen_id y destino_id deben ser IDs distintos y existentes; no generes enlaces de un nodo hacia sí mismo.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'top_p' => 1,
            ]);

        if ($response->successful()) {
            $content = $response->json('choices.0.message.content');
            $decoded = $this->extractJsonRobust($content);

            if ($decoded !== null) {
                try {
                    $service = new CaseAnalysisService();

                    switch ($step) {
                        case 'extract_facts':
                            $decoded = $service->normalizeHechos($decoded);
                            break;
                        case 'generate_diagram':
                            $contextNodes = json_decode((string) $context, true);
                            $lesionId = null;
                            foreach (is_array($contextNodes) ? $contextNodes : [] as $node) {
                                if (($node['tipo_nodo'] ?? '') === 'lesion') {
                                    $lesionId = (int) ($node['id'] ?? 0);
                                    break;
                                }
                            }
                            $decoded = $service->normalizeEnlaces(
                                $decoded,
                                $lesionId ?: null,
                                is_array($contextNodes) ? $contextNodes : []
                            );
                            break;
                        case 'generate_measures':
                            $decoded = $service->normalizeMedidas($decoded);
                            break;
                    }

                    return response()->json($decoded);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'error' => 'Invalid JSON structure for the requested step',
                        'details' => $e->getMessage(),
                        'raw' => $content,
                    ], 422);
                }
            }

            return response()->json([
                'error' => 'Invalid JSON from LLM',
                'raw' => $content,
                'json_error' => json_last_error_msg()
            ]);
        }

        return response()->json(['error' => 'Error from Modelo Local', 'details' => $response->json()], 500);
    }

    private function extractJsonRobust(?string $content)
    {
        $content = preg_replace('/```(?:json)?/i', '', $content);
        $content = trim($content);

        $cleaned = preg_replace('/,\s*([\]}])/m', '$1', $content);

        $startArr = strpos($cleaned, '[');
        $endArr = strrpos($cleaned, ']');
        if ($startArr !== false && $endArr !== false && $endArr > $startArr) {
            $possibleArray = substr($cleaned, $startArr, $endArr - $startArr + 1);
            $decoded = json_decode($possibleArray, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        if (preg_match_all('/\{[^{}]+\}/s', $cleaned, $matches)) {
            $arr = [];
            foreach ($matches[0] as $match) {
                $obj = json_decode($match, true);
                if (is_array($obj)) {
                    $arr[] = $obj;
                }
            }

            if (count($arr) > 0) {
                return $arr;
            }
        }

        return null;
    }

    private function getPrompt(string $step, ?string $relato, ?string $context)
    {
        switch ($step) {
            case 'extract_facts':
                return <<<PROMPT
Actúa como un analista experto en Prevención de Riesgos Laborales y en metodología de Árbol de Causas. Tu misión es transformar el relato en una lista de hechos causalmente relevantes, objetiva, breve y jerárquica.

INSTRUCCIONES DE ANÁLISIS:
1. Identifica el evento final y resumelo en un único nodo de tipo 'lesion'.
2. El nodo 'lesion' debe representar únicamente el daño final, por ejemplo dolor, hormigueo, fractura, esguince o trastorno musculoesquelético.
3. Busca condiciones permanentes y factores de riesgo previos: infraestructura, equipamiento, ergonomía, procedimientos, condiciones del puesto, supervisión, gestión, controles, organización del trabajo, etc.
4. Separa hechos compuestos en hechos simples, concretos y verificables.
5. Redacta cada descripción de forma concreta y técnica, con un máximo de 12 palabras por nodo.
6. No copies frases literales largas del relato. Debes resumir con precisión técnica.
7. Excluye todo hecho posterior a la lesión: decisión de acudir a ACHS, atención médica, evaluación médica, estudios, reposo, derivación, tratamiento y seguimiento clínico.
8. Ordena los hechos con lógica causal, no solo cronológica.
9. Elabora un análisis completo: entrega entre 8 y 14 nodos, incluyendo al menos 2 nodos 'permanente', 1 nodo 'lesion', hechos explícitos e hipótesis causales.
10. Prioriza factores que expliquen el accidente y descarta elementos secundarios o anecdóticos.
11. Si el relato incluye ergonomía, puesto temporal, mobiliario, tareas administrativas o trabajos en altura, desglosa sus causas específicas con rigor técnico.
12. No inventes hechos no sustentados por el relato. Solo infiere lo mínimo y razonable.
13. Debes inferir rutas probables del evento, antecedentes previos y condiciones que pudieron haber contribuido aunque no aparezcan como hechos explícitos del relato.
14. Incluye entre 1 y 4 nodos 'hipotesis' cuando falte un eslabón causal necesario. Cada hipótesis debe explicar cómo se relacionan hechos existentes y debe ser verificable mediante entrevista, observación o documentación.
15. Una hipótesis no es un hecho confirmado: redacta su descripción con lenguaje prudente, por ejemplo 'Posible falta de...' o 'Probable relación entre...'. No inventes detalles específicos, responsables ni incumplimientos no mencionados.
16. Para un caso ergonómico, analiza por separado: condición del puesto, deficiencia del mobiliario, exigencia de la tarea, exposición o repetición, respuesta del trabajador y mecanismo de daño.
17. No agrupes en un solo nodo conceptos distintos como puesto, mobiliario, tarea y lesión.
18. La lesión debe ser el último nodo y debe existir una secuencia causal completa desde las condiciones permanentes hasta el daño.
19. No uses nombres propios de trabajadores como hechos; registra solo condiciones laborales, tareas, exposiciones y daños sustentados por el relato.
20. Nunca redactes la lesión como una evaluación médica, indicación de reposo o tratamiento. El daño debe expresarse sin mencionar la atención posterior.

NORMAS CRÍTICAS:
- La salida debe ser SOLO un array JSON válido.
- No escribas texto adicional fuera del JSON.
- No uses markdown ni explicaciones.
- Cada elemento debe respetar este esquema:
[
    { "id": 1, "descripcion": "condición causal específica o hipótesis verificable", "tipo_nodo": "permanente|hecho|hipotesis|lesion" }
]

Relato real:
$relato
PROMPT;

            case 'generate_diagram':
                return <<<PROMPT
Eres un sistema de modelado causal para Prevención de Riesgos. Tu tarea es conectar los nodos del árbol de causas siguiendo una lógica técnica, racional y válida.

NORMAS ESTRICTAS:
1. Cada enlace debe ser un objeto JSON: origen_id, destino_id, tipo_relacion.
2. La relación causal debe ir de causa a efecto: origen = antecedente, destino = consecuencia.
3. Conecta todos los nodos relevantes y termina en el nodo de lesión. Ninguna rama puede quedar desconectada.
4. Construye un diagrama desarrollado, no una cadena mínima: utiliza todos los nodos recibidos y conserva las causas permanentes.
5. Usa 'cadena' para secuencias causales lineales y 'conjuncion' para causas paralelas que interactúan.
6. Usa convergencia real cuando dos o más causas expliquen un mismo hecho; no unas todo directamente a la lesión.
7. Las causas permanentes deben conectarse primero con hechos intermedios que expliquen cómo generaron la exposición o el evento.
8. Para ergonomía, representa la secuencia puesto o mobiliario -> postura o exigencia -> exposición o repetición -> respuesta física -> lesión, usando los nodos disponibles.
9. No inventes conexiones que no se deduzcan de los hechos. La lógica debe ser sólida y defendible.
10. Además de la secuencia explícita, incorpora las rutas probables de agravamiento y los antecedentes previos que expliquen cómo se pudo llegar al evento o a la lesión.
11. No agregues enlaces entre un nodo y sí mismo ni enlaces duplicados.
12. La salida debe ser únicamente JSON puro, sin texto adicional, sin markdown, sin comentarios.
13. tipo_relacion solo puede ser exactamente 'cadena', 'conjuncion' o 'disyuncion'. 'hipotesis' es un tipo_nodo, nunca un tipo_relacion. Nunca uses nombres de nodos o etapas como 'condicion', 'actividad', 'exposicion', 'repeticion', 'efecto', 'evolucion', 'hipotesis' o 'lesion' como tipo_relacion.
14. Si una causa se bifurca en dos ramas, ambas ramas deben volver a converger mediante 'conjuncion' antes de llegar a la lesión.
15. No dejes ningún nodo con salida hacia un final distinto de la lesión. El único nodo sin enlaces salientes debe ser el nodo tipo 'lesion'.
16. Antes de responder, verifica que cada ID recibido participe en el diagrama y que exista al menos un nodo destino con dos orígenes distintos.
17. El conjunto de enlaces debe tener exactamente un nodo terminal: la lesión. Si existe una rama que termina en otro ID, agrega enlaces causales desde esa rama hasta la lesión.

Lista de hechos reales:
$context

Genera el array JSON de enlaces con la estructura causal correcta.
PROMPT;

            case 'generate_measures':
                return <<<PROMPT
Actúa como un especialista en Prevención de Riesgos. Tu misión es proponer medidas preventivas estrictamente alineadas con las causas permanentes del accidente.

REGLAS:
1. Devuelve solo un array JSON de strings.
2. Cada medida debe estar directamente relacionada con un nodo tipo 'permanente'.
3. Las medidas deben ser específicas, viables y accionables.
4. No uses medidas genéricas o ajenas al caso.
5. No agregues texto fuera del JSON.
6. Mantén la redacción breve, técnica y útil.

Formato exacto:
["Medida específica 1", "Medida específica 2"]

Contexto del accidente:
$context
PROMPT;

            default:
                return "";
        }
    }
}

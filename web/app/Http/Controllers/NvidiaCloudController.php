<?php

namespace App\Http\Controllers;

use App\Services\CaseAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NvidiaCloudController extends Controller
{
    public function analyze(Request $request)
    {
        set_time_limit(0);

        $step = $request->input('step');
        $relato = $request->input('relato');
        $context = $request->input('context');
        $requestedModel = $request->input('model');
        $prompt = $this->getPrompt($step, $relato, $context);

        $apiUrl = 'https://integrate.api.nvidia.com/v1/chat/completions';
        $token = trim(env('NVIDIA_CLOUD_API_KEY'));

        $model = $requestedModel ?: 'nvidia/nemotron-3-super-120b-a12b';

        $response = Http::withToken($token)
            ->timeout(0)
            ->post($apiUrl, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Responde ÚNICAMENTE con un Array JSON válido. No uses markdown ni texto adicional.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'top_p' => 1,
                'max_tokens' => 1024,
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
                            $decoded = $service->normalizeEnlaces($decoded);
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

        \Illuminate\Support\Facades\Log::error("Nvidia Cloud API Error: " . $response->body());
        return response()->json(['error' => 'Error from Nvidia Cloud API', 'details' => $response->json()], 500);
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
            if ($decoded !== null) return $decoded;
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
2. El nodo 'lesion' debe representar únicamente el daño final, sin incluir atención médica, estudios, reposo ni evaluaciones posteriores.
3. Busca condiciones permanentes y factores de riesgo previos: infraestructura, equipamiento, ergonomía, procedimientos, condiciones del puesto, supervisión, gestión, controles, organización del trabajo, etc.
4. Separa hechos compuestos en hechos simples, concretos y verificables.
5. Redacta cada descripción con máxima brevedad: máximo 8 palabras por nodo.
6. No copies frases literales largas del relato. Debes resumir con precisión técnica.
7. Excluye todo hecho posterior a la lesión: ACHS, atención médica, reposo, derivación, tratamiento, seguimiento clínico, etc.
8. Ordena los hechos con lógica causal, no solo cronológica.
9. Asegura que exista al menos 1 nodo 'permanente' y al menos 5 hechos en total.
10. Prioriza factores que expliquen el accidente y descarta elementos secundarios o anecdóticos.
11. Si el relato incluye ergonomía, puesto temporal, mobiliario, tareas administrativas o trabajos en altura, debes inferir la causa subyacente con rigor técnico.
12. No inventes hechos no sustentados por el relato. Solo infiere lo mínimo y razonable.
13. Debes inferir rutas probables del evento, antecedentes previos y condiciones que pudieron haber contribuido aunque no aparezcan como hechos explícitos del relato.

NORMAS CRÍTICAS:
- La salida debe ser SOLO un array JSON válido.
- No escribas texto adicional fuera del JSON.
- No uses markdown ni explicaciones.
- El formato exacto debe ser:
[
  { "id": 1, "descripcion": "...", "tipo_nodo": "permanente" },
  { "id": 2, "descripcion": "...", "tipo_nodo": "hecho" },
  { "id": 3, "descripcion": "...", "tipo_nodo": "lesion" }
]

EJEMPLO DE REFERENCIA:
Relato: 'Un administrativo se tropieza con un cable suelto en la oficina. Había un alargador temporal y faltaban enchufes.'
Salida:
[
  { "id": 1, "descripcion": "Falta de enchufes en pared", "tipo_nodo": "permanente" },
  { "id": 2, "descripcion": "Instalación eléctrica provisional", "tipo_nodo": "permanente" },
  { "id": 3, "descripcion": "Cable suelto en tránsito", "tipo_nodo": "hecho" },
  { "id": 4, "descripcion": "Tropiezo con cable suelto", "tipo_nodo": "hecho" },
  { "id": 5, "descripcion": "Esguince de tobillo", "tipo_nodo": "lesion" }
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
3. El nodo de lesión debe ser el destino final de todas las ramas. Ninguna rama puede quedar desconectada.
4. Debe existir convergencia real: al menos una rama con 'conjuncion' que una dos o más causas hacia un efecto común.
5. Usa 'cadena' para secuencias causales lineales y 'conjuncion' para causas paralelas que interactúan.
6. Conecta todos los nodos relevantes. No dejes hechos aislados si tienen relación causal con la lesión.
7. No inventes conexiones que no se deduzcan de los hechos. La lógica debe ser sólida y defensible.
8. Prioriza la causalidad del accidente y no elementos secundarios o post-lesión.
9. La salida debe ser únicamente JSON puro, sin texto adicional, sin markdown, sin comentarios.
10. Si la lista incluye condiciones permanentes, deben conectarse a hechos posteriores que se explican por ellas.

EJEMPLO:
[
  { "origen_id": 1, "destino_id": 3, "tipo_relacion": "conjuncion" },
  { "origen_id": 2, "destino_id": 3, "tipo_relacion": "conjuncion" },
  { "origen_id": 3, "destino_id": 4, "tipo_relacion": "cadena" },
  { "origen_id": 4, "destino_id": 5, "tipo_relacion": "cadena" }
]

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

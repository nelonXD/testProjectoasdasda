<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NvidiaApiController extends Controller
{
    public function process(Request $request)
    {
        $step = $request->input('step');
        $relato = $request->input('relato');
        $context = $request->input('context', '');

        $prompt = $this->getPrompt($step, $relato, $context);

        $response = Http::withToken(env('NVIDIA_API_KEY'))
            ->post('https://integrate.api.nvidia.com/v1/chat/completions', [
                'model' => 'meta/llama-3.2-90b-vision-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'top_p' => 1,
                'max_tokens' => 1024,
            ]);

        if ($response->successful()) {
            $content = $response->json('choices.0.message.content');
            // Intentar limpiar el JSON si el LLM incluyó markdown
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            
            return response()->json(json_decode($content, true) ?? ['error' => 'Invalid JSON from LLM']);
        }

        return response()->json(['error' => 'Error from Nvidia API', 'details' => $response->json()], 500);
    }

    private function getPrompt($step, $relato, $context)
    {
        switch ($step) {
            case 'extract_facts':
                return "Dado el siguiente relato de un accidente laboral, extrae los hechos más relevantes como una lista secuencial. 
Devuelve EXCLUSIVAMENTE un arreglo JSON con el siguiente formato:
[
  {\"id\": \"h1\", \"descripcion\": \"El trabajador resbala\", \"tipo\": \"hecho\"}
]
Relato: $relato";

            case 'generate_diagram':
                return "Dado el siguiente relato y lista de hechos, genera las conexiones causales (Árbol de Causas) de derecha a izquierda (la lesión al final). 
Devuelve EXCLUSIVAMENTE un JSON con el formato:
{
  \"nodos\": [{\"id\": \"h1\", \"descripcion\": \"...\"}],
  \"enlaces\": [{\"origen\": \"h1\", \"destino\": \"h2\"}]
}
Relato: $relato
Hechos Previos: $context";

            case 'generate_measures':
                return "Basado en el siguiente diagrama de causas de un accidente, genera medidas preventivas para las causas raíz identificadas.
Devuelve EXCLUSIVAMENTE un JSON con formato:
[
  {\"causa_id\": \"h1\", \"medida\": \"Colocar cintas antideslizantes\"}
]
Contexto del Diagrama: $context";

            default:
                return "Hola";
        }
    }
}

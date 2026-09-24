<?php

namespace Tests\Feature;

use App\Services\CaseAnalysisService;
use Tests\TestCase;

class CaseAnalysisServiceTest extends TestCase
{
    public function test_it_normalizes_valid_fact_nodes(): void
    {
        $service = new CaseAnalysisService();

        $nodes = [
            ['id' => 1, 'descripcion' => 'Falta de señalización', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Piso mojado', 'tipo_nodo' => 'permanente'],
            ['id' => 3, 'descripcion' => 'Trabajador camina por pasillo', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Trabajador resbala', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Contusión en la rodilla', 'tipo_nodo' => 'lesion'],
        ];

        $normalized = $service->normalizeHechos($nodes);

        $this->assertCount(5, $normalized);
        $this->assertSame('permanente', $normalized[0]['tipo_nodo']);
        $this->assertSame('lesion', $normalized[4]['tipo_nodo']);
    }

    public function test_it_normalizes_common_model_aliases_for_node_types(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Condición del puesto', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Exposición a la tarea', 'tipo_nodo' => 'exclusiva'],
            ['id' => 3, 'descripcion' => 'Movimiento repetido', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Dolor durante la jornada', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Dolor lumbar', 'tipo_nodo' => 'lesion'],
        ]);

        $this->assertSame('hecho', $normalized[1]['tipo_nodo']);
    }

    public function test_it_preserves_hypothesis_nodes_as_distinct_causal_elements(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Paciente menor de edad', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Dificultad de manejo durante la atención', 'tipo_nodo' => 'hecho'],
            ['id' => 3, 'descripcion' => 'Posible necesidad de contención adicional', 'tipo_nodo' => 'hipotesis'],
            ['id' => 4, 'descripcion' => 'Mordedura durante la atención', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Corte en la mano', 'tipo_nodo' => 'hecho'],
            ['id' => 6, 'descripcion' => 'Herida con sangrado', 'tipo_nodo' => 'lesion'],
        ]);

        $this->assertSame('hipotesis', $normalized[2]['tipo_nodo']);
    }

    public function test_it_promotes_a_stable_condition_to_permanent_when_model_omits_it(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Paciente menor de 2 años', 'tipo_nodo' => 'hecho'],
            ['id' => 2, 'descripcion' => 'Atención individual del paciente', 'tipo_nodo' => 'hecho'],
            ['id' => 3, 'descripcion' => 'Posible dificultad de manejo', 'tipo_nodo' => 'hipotesis'],
            ['id' => 4, 'descripcion' => 'Mordedura durante la atención', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Corte en la mano', 'tipo_nodo' => 'hecho'],
            ['id' => 6, 'descripcion' => 'Herida con sangrado', 'tipo_nodo' => 'lesion'],
        ]);

        $this->assertSame('permanente', $normalized[0]['tipo_nodo']);
    }

    public function test_it_removes_duplicate_fact_descriptions(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Puesto sin supervisión', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Paciente muerde repetidamente', 'tipo_nodo' => 'hecho'],
            ['id' => 3, 'descripcion' => 'Paciente  muerde repetidamente', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Corte en la mano', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Guantes dificultan el manejo', 'tipo_nodo' => 'hecho'],
            ['id' => 6, 'descripcion' => 'Sangrado de la mano', 'tipo_nodo' => 'lesion'],
        ]);

        $descriptions = array_column($normalized, 'descripcion');

        $this->assertCount(5, $normalized);
        $this->assertCount(1, array_filter($descriptions, fn ($description) => str_contains($description, 'muerde')));
    }

    public function test_it_rejects_fact_nodes_without_final_lesion(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debe existir exactamente un nodo final tipo lesion');

        $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Falta de señalización', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Trabajador camina por pasillo', 'tipo_nodo' => 'hecho'],
        ]);
    }

    public function test_it_rejects_trees_that_are_too_short_to_be_causal(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El árbol causal es demasiado corto');

        $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Piso húmedo', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Caída del trabajador', 'tipo_nodo' => 'lesion'],
        ]);
    }

    public function test_it_moves_the_lesion_to_the_end_and_removes_post_event_care(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Funcionario trabaja en puesto temporal', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Dolor de espalda y cuello', 'tipo_nodo' => 'lesion'],
            ['id' => 3, 'descripcion' => 'Solicita cambio de box temporal', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Box facilitado no cuenta con escritorio', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Mobiliario inadecuado para el trabajo', 'tipo_nodo' => 'permanente'],
            ['id' => 6, 'descripcion' => 'Exposición prolongada a las condiciones del puesto', 'tipo_nodo' => 'hecho'],
            ['id' => 7, 'descripcion' => 'Funcionario decide acudir a ACHS', 'tipo_nodo' => 'hecho'],
        ]);

        $this->assertSame('lesion', $normalized[array_key_last($normalized)]['tipo_nodo']);
        $this->assertCount(6, $normalized);
        $this->assertStringNotContainsString('ACHS', json_encode($normalized));
    }

    public function test_it_promotes_the_last_symptom_when_lesion_contains_medical_care(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeHechos([
            ['id' => 1, 'descripcion' => 'Puesto administrativo temporal', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Mobiliario sin regulación ergonómica', 'tipo_nodo' => 'permanente'],
            ['id' => 3, 'descripcion' => 'Postura forzada durante digitación', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Exposición prolongada a la tarea', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Tensión cervical y dolor lumbar', 'tipo_nodo' => 'hecho'],
            ['id' => 6, 'descripcion' => 'Síntomas progresivos con hormigueo', 'tipo_nodo' => 'hecho'],
            ['id' => 7, 'descripcion' => 'Evaluación médica indicó reposo y tratamiento musculoesquelético', 'tipo_nodo' => 'lesion'],
        ]);

        $this->assertSame('lesion', $normalized[array_key_last($normalized)]['tipo_nodo']);
        $this->assertSame('Síntomas progresivos con hormigueo', $normalized[array_key_last($normalized)]['descripcion']);
        $this->assertStringNotContainsString('tratamiento', strtolower(json_encode($normalized)));
    }

    public function test_it_includes_inference_guidance_for_causal_paths(): void
    {
        $controller = new \App\Http\Controllers\ModeloLocalController();
        $method = new \ReflectionMethod($controller, 'getPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($controller, 'extract_facts', 'Relato de prueba', '{}');

        $this->assertStringContainsString('inferir', strtolower($prompt));
        $this->assertStringContainsString('rutas probables', strtolower($prompt));
        $this->assertStringContainsString('antecedentes previos', strtolower($prompt));
    }

    public function test_it_normalizes_valid_links(): void
    {
        $service = new CaseAnalysisService();

        $links = [
            ['origen_id' => 1, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 2, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 3, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
        ];

        $normalized = $service->normalizeEnlaces($links);

        $this->assertCount(3, $normalized);
        $this->assertSame('conjuncion', $normalized[0]['tipo_relacion']);
    }

    public function test_it_normalizes_semantic_relationship_labels_from_the_local_model(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeEnlaces([
            ['origen_id' => 1, 'destino_id' => 1, 'tipo_relacion' => 'condicion'],
            ['origen_id' => 2, 'destino_id' => 1, 'tipo_relacion' => 'condicion'],
            ['origen_id' => 3, 'destino_id' => 1, 'tipo_relacion' => 'condicion'],
            ['origen_id' => 1, 'destino_id' => 4, 'tipo_relacion' => 'actividad'],
            ['origen_id' => 4, 'destino_id' => 5, 'tipo_relacion' => 'exposicion'],
            ['origen_id' => 5, 'destino_id' => 6, 'tipo_relacion' => 'lesion'],
        ]);

        $this->assertCount(5, $normalized);
        $this->assertSame('cadena', $normalized[0]['tipo_relacion']);
        $this->assertSame('cadena', $normalized[2]['tipo_relacion']);
    }

    public function test_it_converts_hypothesis_relation_alias_to_chain(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeEnlaces([
            ['origen_id' => 1, 'destino_id' => 2, 'tipo_relacion' => 'hipotesis'],
            ['origen_id' => 3, 'destino_id' => 2, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 2, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
        ]);

        $this->assertSame('cadena', $normalized[0]['tipo_relacion']);
    }

    public function test_it_connects_isolated_nodes_when_normalizing_the_diagram(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeEnlaces([
            ['origen_id' => 2, 'destino_id' => 3, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 6, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 3, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
        ], 4, [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
            ['id' => 5],
            ['id' => 6],
        ]);

        $this->assertContains(['origen_id' => 1, 'destino_id' => 2, 'tipo_relacion' => 'cadena'], $normalized);
        $this->assertContains(['origen_id' => 5, 'destino_id' => 6, 'tipo_relacion' => 'cadena'], $normalized);
    }

    public function test_it_rejects_chain_only_diagrams_without_branching(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('estructura de causa con convergencia');

        $service->normalizeEnlaces([
            ['origen_id' => 1, 'destino_id' => 2, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 2, 'destino_id' => 3, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 3, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 4, 'destino_id' => 5, 'tipo_relacion' => 'cadena'],
        ]);
    }

    public function test_it_rejects_branching_without_convergence(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('estructura de causa con convergencia');

        $service->normalizeEnlaces([
            ['origen_id' => 2, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 3, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 4, 'destino_id' => 5, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 5, 'destino_id' => 6, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 6, 'destino_id' => 8, 'tipo_relacion' => 'disyuncion'],
            ['origen_id' => 4, 'destino_id' => 9, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 9, 'destino_id' => 10, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 10, 'destino_id' => 11, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 11, 'destino_id' => 12, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 12, 'destino_id' => 13, 'tipo_relacion' => 'disyuncion'],
        ]);
    }

    public function test_it_rejects_diagrams_with_two_terminal_nodes(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('único nodo final');

        $service->normalizeEnlaces([
            ['origen_id' => 1, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 2, 'destino_id' => 5, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 3, 'destino_id' => 5, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 4, 'destino_id' => 6, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 5, 'destino_id' => 8, 'tipo_relacion' => 'cadena'],
        ]);
    }

    public function test_it_connects_an_extra_terminal_to_the_lesion(): void
    {
        $service = new CaseAnalysisService();

        $normalized = $service->normalizeEnlaces([
            ['origen_id' => 2, 'destino_id' => 10, 'tipo_relacion' => 'disyuncion'],
            ['origen_id' => 3, 'destino_id' => 10, 'tipo_relacion' => 'disyuncion'],
            ['origen_id' => 4, 'destino_id' => 7, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 5, 'destino_id' => 8, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 6, 'destino_id' => 10, 'tipo_relacion' => 'disyuncion'],
            ['origen_id' => 7, 'destino_id' => 8, 'tipo_relacion' => 'cadena'],
        ], 8);

        $this->assertSame(['origen_id' => 10, 'destino_id' => 8, 'tipo_relacion' => 'cadena'], end($normalized));
    }

    public function test_it_rejects_invalid_relationship_type(): void
    {
        $service = new CaseAnalysisService();

        $this->expectException(\InvalidArgumentException::class);

        $service->normalizeEnlaces([
            ['origen_id' => 1, 'destino_id' => 2, 'tipo_relacion' => 'relacion_invalida'],
        ]);
    }

    public function test_it_accepts_a_valid_causal_structure_for_the_back_pain_story(): void
    {
        $service = new CaseAnalysisService();

        $hechos = [
            ['id' => 1, 'descripcion' => 'Box temporal sin escritorio', 'tipo_nodo' => 'permanente'],
            ['id' => 2, 'descripcion' => 'Mobiliario inadecuado para estadistica', 'tipo_nodo' => 'permanente'],
            ['id' => 3, 'descripcion' => 'Funcionario trabaja en puesto temporal', 'tipo_nodo' => 'hecho'],
            ['id' => 4, 'descripcion' => 'Postura forzada durante trabajo prolongado', 'tipo_nodo' => 'hecho'],
            ['id' => 5, 'descripcion' => 'Dolor de espalda y cuello', 'tipo_nodo' => 'lesion'],
        ];

        $enlaces = [
            ['origen_id' => 1, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 2, 'destino_id' => 3, 'tipo_relacion' => 'conjuncion'],
            ['origen_id' => 3, 'destino_id' => 4, 'tipo_relacion' => 'cadena'],
            ['origen_id' => 4, 'destino_id' => 5, 'tipo_relacion' => 'cadena'],
        ];

        $this->assertSame('lesion', $service->normalizeHechos($hechos)[4]['tipo_nodo']);
        $this->assertCount(4, $service->normalizeEnlaces($enlaces));
    }
}

<?php

namespace App\Services;

use InvalidArgumentException;

class CaseAnalysisService
{
    private const VALID_NODE_TYPES = ['permanente', 'hecho', 'hipotesis', 'lesion'];
    private const VALID_RELATION_TYPES = ['cadena', 'conjuncion', 'disyuncion'];

    public function normalizeHechos(array $hechos): array
    {
        if (!is_array($hechos) || $hechos === []) {
            throw new InvalidArgumentException('La lista de hechos no puede estar vacía.');
        }

        $normalized = [];
        $lesionCount = 0;

        foreach ($hechos as $index => $nodo) {
            if (!is_array($nodo)) {
                throw new InvalidArgumentException('Cada hecho debe ser un objeto JSON válido.');
            }

            $descripcion = trim((string) ($nodo['descripcion'] ?? ''));
            $tipo = strtolower(trim((string) ($nodo['tipo_nodo'] ?? '')));

            $tipo = match ($tipo) {
                'causa', 'causal', 'condicion', 'condición', 'intermedio', 'intermedia', 'evento', 'exclusiva' => 'hecho',
                'condicion permanente', 'condición permanente', 'factor permanente' => 'permanente',
                'hipótesis', 'inferencia', 'inferida', 'probable' => 'hipotesis',
                'daño', 'dano', 'síntoma', 'sintoma' => 'lesion',
                default => $tipo,
            };

            if ($descripcion === '') {
                throw new InvalidArgumentException('Cada nodo debe tener una descripción válida.');
            }

            if (!in_array($tipo, self::VALID_NODE_TYPES, true)) {
                throw new InvalidArgumentException("Tipo de nodo inválido en el hecho #{$index}: {$tipo}");
            }

            if ($tipo === 'lesion') {
                $lesionCount++;
            }

            $normalized[] = [
                'id' => (int) ($nodo['id'] ?? $index + 1),
                'descripcion' => $descripcion,
                'tipo_nodo' => $tipo,
            ];
        }

        if ($lesionCount > 1) {
            throw new InvalidArgumentException('Debe existir exactamente un nodo final tipo lesion');
        }

        // El modelo puede clasificar como lesión una frase que mezcla el daño con la atención posterior.
        $normalized = array_values(array_filter($normalized, function (array $nodo): bool {
            $descripcion = strtolower($nodo['descripcion']);

            return !preg_match('/achs|atenci[oó]n m[eé]dica|reposo|derivaci[oó]n|tratamiento|seguimiento|consulta m[eé]dica|evaluaci[oó]n m[eé]dica|hospitalizaci[oó]n/', $descripcion);
        }));

        $uniqueNodes = [];
        $seenDescriptions = [];
        foreach ($normalized as $nodo) {
            $key = preg_replace('/\s+/', ' ', strtolower(trim($nodo['descripcion'])));

            if (isset($seenDescriptions[$key])) {
                $existingIndex = $seenDescriptions[$key];
                if ($nodo['tipo_nodo'] === 'lesion' && $uniqueNodes[$existingIndex]['tipo_nodo'] !== 'lesion') {
                    $uniqueNodes[$existingIndex] = $nodo;
                }
                continue;
            }

            $seenDescriptions[$key] = count($uniqueNodes);
            $uniqueNodes[] = $nodo;
        }
        $normalized = $uniqueNodes;

        $lesion = null;
        $causalNodes = [];
        foreach ($normalized as $nodo) {
            if ($nodo['tipo_nodo'] === 'lesion') {
                $lesion = $nodo;
                continue;
            }

            $causalNodes[] = $nodo;
        }

        if ($lesion === null) {
            for ($index = count($causalNodes) - 1; $index >= 0; $index--) {
                $descripcion = strtolower($causalNodes[$index]['descripcion']);

            if (preg_match('/dolor|molestia|hormigueo|entumecimiento|herida|sangrado|lesi[oó]n|fractura|esguince|contusi[oó]n|s[ií]ntoma|afecci[oó]n|trastorno/', $descripcion)) {
                    $lesion = $causalNodes[$index];
                    $lesion['tipo_nodo'] = 'lesion';
                    unset($causalNodes[$index]);
                    $causalNodes = array_values($causalNodes);
                    break;
                }
            }
        }

        if ($lesion === null) {
            throw new InvalidArgumentException('Debe existir exactamente un nodo final tipo lesion');
        }

        $causalNodes[] = $lesion;
        $normalized = $causalNodes;

        $permanentes = count(array_filter($normalized, fn ($nodo) => ($nodo['tipo_nodo'] ?? '') === 'permanente'));

        if ($permanentes === 0) {
            foreach ($normalized as $index => $nodo) {
                if ($nodo['tipo_nodo'] === 'lesion') {
                    continue;
                }

                $descripcion = strtolower($nodo['descripcion']);
                if (preg_match('/menor|edad|año|anos|protocolo|infraestructura|supervisi[oó]n|organizaci[oó]n|equipamiento|mobiliario|puesto|condici[oó]n previa|antecedente/', $descripcion)) {
                    $normalized[$index]['tipo_nodo'] = 'permanente';
                    $permanentes = 1;
                    break;
                }
            }
        }

        if (count($normalized) < 5) {
            throw new InvalidArgumentException('El árbol causal es demasiado corto. Debe incluir al menos 5 hechos para ser válido.');
        }

        if ($permanentes < 1) {
            throw new InvalidArgumentException('El árbol causal debe incluir al menos una causa permanente.');
        }

        $last = end($normalized);
        if ($last === false || ($last['tipo_nodo'] ?? '') !== 'lesion') {
            throw new InvalidArgumentException('El último nodo debe ser una lesión.');
        }

        return $normalized;
    }

    public function normalizeEnlaces(array $enlaces, ?int $lesionId = null, array $nodos = []): array
    {
        if (!is_array($enlaces)) {
            throw new InvalidArgumentException('Los enlaces deben ser un arreglo JSON válido.');
        }

        $normalized = [];

        foreach ($enlaces as $index => $enlace) {
            if (!is_array($enlace)) {
                throw new InvalidArgumentException('Cada enlace debe ser un objeto JSON válido.');
            }

            $origen = (int) ($enlace['origen_id'] ?? $enlace['origen'] ?? 0);
            $destino = (int) ($enlace['destino_id'] ?? $enlace['destino'] ?? 0);
            $tipo = strtolower(trim((string) ($enlace['tipo_relacion'] ?? '')));

            // Algunos modelos describen la relación en vez de usar el enum del diagrama.
            $tipo = match ($tipo) {
                'condicion', 'condición', 'actividad', 'exposicion', 'exposición', 'repeticion', 'repetición',
                'efecto', 'evolucion', 'evolución', 'lesion', 'lesión', 'hipotesis', 'hipótesis',
                'hecho', 'permanente' => 'cadena',
                default => $tipo,
            };

            if ($origen <= 0 || $destino <= 0) {
                throw new InvalidArgumentException("Enlace inválido en la posición #{$index}: faltan origen/destino.");
            }

            if ($origen === $destino) {
                continue;
            }

            if (!in_array($tipo, self::VALID_RELATION_TYPES, true)) {
                throw new InvalidArgumentException("Tipo de relación inválida en el enlace #{$index}: {$tipo}");
            }

            $normalized[] = [
                'origen_id' => $origen,
                'destino_id' => $destino,
                'tipo_relacion' => $tipo,
            ];
        }

        if ($nodos !== []) {
            $connectedIds = [];
            foreach ($normalized as $link) {
                $connectedIds[$link['origen_id']] = true;
                $connectedIds[$link['destino_id']] = true;
            }

            $nodeIds = array_values(array_filter(array_map(
                fn ($nodo) => (int) ($nodo['id'] ?? 0),
                $nodos
            ), fn (int $id) => $id > 0));

            foreach ($nodeIds as $index => $nodeId) {
                if ($nodeId === $lesionId || isset($connectedIds[$nodeId])) {
                    continue;
                }

                $nextNodeId = null;
                for ($nextIndex = $index + 1; $nextIndex < count($nodeIds); $nextIndex++) {
                    if ($nodeIds[$nextIndex] !== $nodeId && $nodeIds[$nextIndex] !== $lesionId) {
                        $nextNodeId = $nodeIds[$nextIndex];
                        break;
                    }
                }

                $nextNodeId ??= $lesionId;
                if ($nextNodeId === null || $nextNodeId === $nodeId) {
                    continue;
                }

                $normalized[] = [
                    'origen_id' => $nodeId,
                    'destino_id' => $nextNodeId,
                    'tipo_relacion' => 'cadena',
                ];
                $connectedIds[$nodeId] = true;
            }
        }

        if ($lesionId !== null && $lesionId > 0) {
            $destinos = array_column($normalized, 'destino_id');
            $origenes = array_column($normalized, 'origen_id');
            $terminales = array_values(array_diff(array_unique($destinos), array_unique($origenes)));

            foreach ($terminales as $terminal) {
                if ($terminal !== $lesionId) {
                    $normalized[] = [
                        'origen_id' => $terminal,
                        'destino_id' => $lesionId,
                        'tipo_relacion' => 'cadena',
                    ];
                }
            }
        }

        $ids = [];
        foreach ($normalized as $link) {
            $ids[] = $link['origen_id'];
            $ids[] = $link['destino_id'];
        }

        if (count($normalized) < 3) {
            throw new InvalidArgumentException('La estructura de causa es insuficiente. Debe existir una ramificación real hacia la lesión.');
        }

        $destinos = [];
        foreach ($normalized as $link) {
            $destinos[] = $link['destino_id'];
        }

        $origenes = [];
        foreach ($normalized as $link) {
            $origenes[] = $link['origen_id'];
        }

        $distinctNodes = count(array_unique(array_merge($ids)));
        $convergencias = count(array_unique(array_intersect($destinos, $origenes)));
        $maxFanIn = 0;
        $fanInMap = [];
        foreach ($destinos as $destino) {
            $fanInMap[$destino] = ($fanInMap[$destino] ?? 0) + 1;
            $maxFanIn = max($maxFanIn, $fanInMap[$destino]);
        }

        if ($distinctNodes < 4 || $convergencias < 1 || $maxFanIn < 2) {
            throw new InvalidArgumentException('La estructura de causa no cumple una convergencia real hacia la lesión. Debe existir una ramificación y una estructura de causa con convergencia.');
        }

        $roots = [];
        foreach (array_unique(array_merge($origenes, $destinos)) as $nodeId) {
            if (!in_array($nodeId, $destinos, true)) {
                $roots[] = $nodeId;
            }
        }

        $terminales = array_values(array_diff(array_unique($destinos), array_unique($origenes)));
        if (count($terminales) !== 1) {
            throw new InvalidArgumentException('El árbol causal debe tener un único nodo final. Todas las ramas deben converger en la lesión.');
        }

        if (count($roots) < 1) {
            throw new InvalidArgumentException('La estructura de causa no cumple una convergencia real hacia la lesión. Debe existir una ramificación y una estructura de causa con convergencia.');
        }

        return $normalized;
    }

    public function normalizeMedidas(array $medidas): array
    {
        if (!is_array($medidas)) {
            throw new InvalidArgumentException('Las medidas deben ser un arreglo de strings.');
        }

        return array_values(array_filter(array_map(function ($medida) {
            $text = trim((string) $medida);

            if ($text === '') {
                return null;
            }

            return $text;
        }, $medidas)));
    }
}

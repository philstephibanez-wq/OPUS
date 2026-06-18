<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Convert documented Mermaid assets into renderable RefBook diagram ViewModel data.
 *
 * Responsibility:
 *   Parse the supported Mermaid subset used by Opus documentation assets and expose
 *   layout-ready data for Twig/SVG rendering.
 *
 * Contract:
 *   Data preparation only. No HTML rendering, no file lookup, no network call.
 *   Unsupported Mermaid syntaxes remain explicit and keep their source visible.
 */
final class ReferenceDiagramModelBuilder
{
    private const NODE_WIDTH = 178;
    private const NODE_HEIGHT = 66;
    private const DECISION_WIDTH = 170;
    private const DECISION_HEIGHT = 96;
    private const TERMINAL_WIDTH = 78;
    private const TERMINAL_HEIGHT = 50;
    private const LR_X_SPACING = 255;
    private const LR_Y_SPACING = 128;
    private const TD_X_SPACING = 250;
    private const TD_Y_SPACING = 140;
    private const MARGIN_X = 48;
    private const MARGIN_Y = 64;

    /**
     * @param array<string,mixed> $diagramAsset
     * @return array<string,mixed>
     */
    public function build(array $diagramAsset): array
    {
        $id = trim((string) ($diagramAsset['id'] ?? 'diagram'));
        $content = trim((string) ($diagramAsset['content'] ?? ''));

        if ($content === '') {
            return $this->errorModel($id, 'empty', '', 'OPUS_REFBOOK_DIAGRAM_SOURCE_EMPTY');
        }

        $lines = $this->normalizedLines($content);
        $header = $lines[0] ?? '';

        if ($header === 'stateDiagram-v2') {
            return $this->buildStateDiagram($id, $content, array_slice($lines, 1));
        }

        if (preg_match('/^flowchart\s+(LR|TD)$/u', $header, $matches) === 1) {
            return $this->buildFlowchart($id, $content, array_slice($lines, 1), $matches[1]);
        }

        return $this->errorModel(
            $id,
            'unsupported',
            $content,
            'OPUS_REFBOOK_DIAGRAM_FORMAT_NOT_RENDERED=' . $header,
            $header
        );
    }

    /**
     * @return list<string>
     */
    private function normalizedLines(string $content): array
    {
        $raw = preg_split('/\R/u', str_replace("\t", '    ', $content)) ?: [];
        $lines = [];

        foreach ($raw as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '%%')) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @return array<string,mixed>
     */
    private function buildStateDiagram(string $id, string $source, array $lines): array
    {
        $nodes = [];
        $edges = [];

        foreach ($lines as $line) {
            if (!str_contains($line, '-->')) {
                continue;
            }

            if (preg_match('/^(.+?)\s*-->\s*(.+?)(?:\s*:\s*(.+))?$/u', $line, $matches) !== 1) {
                continue;
            }

            $fromRaw = trim($matches[1]);
            $toRaw = trim($matches[2]);
            $label = trim((string) ($matches[3] ?? ''));

            if ($fromRaw === '' || $toRaw === '') {
                continue;
            }

            $from = $this->stateEndpointId($fromRaw, 'from');
            $to = $this->stateEndpointId($toRaw, 'to');

            $this->registerNode($nodes, $from, $this->stateEndpointLabel($fromRaw, 'from'), 'state');
            $this->registerNode($nodes, $to, $this->stateEndpointLabel($toRaw, 'to'), 'state');

            if ($fromRaw === '[*]') {
                $nodes[$from]['shape'] = 'terminal';
            }
            if ($toRaw === '[*]') {
                $nodes[$to]['shape'] = 'terminal';
            }

            $edges[] = [
                'from' => $from,
                'to' => $to,
                'label' => $label,
                'raw' => $line,
            ];
        }

        return $this->layoutModel($id, 'stateDiagram-v2', 'LR', $source, $nodes, $edges);
    }

    /**
     * @param list<string> $lines
     * @return array<string,mixed>
     */
    private function buildFlowchart(string $id, string $source, array $lines, string $orientation): array
    {
        $nodes = [];
        $edges = [];

        foreach ($lines as $line) {
            if (str_contains($line, '-->')) {
                [$left, $right] = explode('-->', $line, 2);
                $left = trim($left);
                $right = trim($right);
                $label = '';

                if (preg_match('/^(.*?)\s+--\s+(.+)$/u', $left, $matches) === 1) {
                    $left = trim($matches[1]);
                    $label = trim($matches[2]);
                }

                $from = $this->parseFlowNodeToken($left);
                $to = $this->parseFlowNodeToken($right);

                if ($from['id'] === '' || $to['id'] === '') {
                    continue;
                }

                $this->registerNode($nodes, $from['id'], $from['label'], $from['shape']);
                $this->registerNode($nodes, $to['id'], $to['label'], $to['shape']);

                $edges[] = [
                    'from' => $from['id'],
                    'to' => $to['id'],
                    'label' => $label,
                    'raw' => $line,
                ];
                continue;
            }

            $node = $this->parseFlowNodeToken($line);
            if ($node['id'] !== '') {
                $this->registerNode($nodes, $node['id'], $node['label'], $node['shape']);
            }
        }

        return $this->layoutModel($id, 'flowchart', $orientation, $source, $nodes, $edges);
    }

    /**
     * @return array{id:string,label:string,shape:string}
     */
    private function parseFlowNodeToken(string $token): array
    {
        $token = trim($token);

        if (preg_match('/^([A-Za-z0-9_.:-]+)\[(.+)\]$/u', $token, $matches) === 1) {
            return ['id' => $matches[1], 'label' => trim($matches[2]), 'shape' => 'process'];
        }

        if (preg_match('/^([A-Za-z0-9_.:-]+)\{(.+)\}$/u', $token, $matches) === 1) {
            return ['id' => $matches[1], 'label' => trim($matches[2]), 'shape' => 'decision'];
        }

        if (preg_match('/^[A-Za-z0-9_.:-]+$/u', $token) === 1) {
            return ['id' => $token, 'label' => $token, 'shape' => 'process'];
        }

        return ['id' => '', 'label' => '', 'shape' => 'process'];
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     */
    private function registerNode(array &$nodes, string $id, string $label, string $shape): void
    {
        if (!isset($nodes[$id])) {
            $nodes[$id] = [
                'id' => $id,
                'label' => $label !== '' ? $label : $id,
                'shape' => $shape,
            ];
            return;
        }

        if (($nodes[$id]['label'] ?? $id) === $id && $label !== '' && $label !== $id) {
            $nodes[$id]['label'] = $label;
        }

        if (($nodes[$id]['shape'] ?? 'process') === 'process' && $shape !== 'process') {
            $nodes[$id]['shape'] = $shape;
        }
    }

    private function stateEndpointId(string $state, string $position): string
    {
        if ($state === '[*]') {
            return $position === 'from' ? '__START__' : '__END__';
        }

        return $state;
    }

    private function stateEndpointLabel(string $state, string $position): string
    {
        if ($state === '[*]') {
            return $position === 'from' ? 'Begin' : 'End';
        }

        return $state;
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param list<array{from:string,to:string,label:string,raw:string}> $edges
     * @return array<string,mixed>
     */
    private function layoutModel(string $id, string $format, string $orientation, string $source, array $nodes, array $edges): array
    {
        $levels = $this->levels($nodes, $edges);
        $grouped = [];

        foreach ($nodes as $nodeId => $node) {
            $level = $levels[$nodeId] ?? 0;
            $grouped[$level][] = $nodeId;
        }

        ksort($grouped);
        $positioned = [];
        $maxX = 0;
        $maxY = 0;

        foreach ($grouped as $level => $nodeIds) {
            foreach (array_values($nodeIds) as $row => $nodeId) {
                $node = $nodes[$nodeId];
                [$width, $height] = $this->nodeSize((string) ($node['shape'] ?? 'process'));

                if ($orientation === 'TD') {
                    $x = self::MARGIN_X + ($row * self::TD_X_SPACING);
                    $y = self::MARGIN_Y + ($level * self::TD_Y_SPACING);
                } else {
                    $x = self::MARGIN_X + ($level * self::LR_X_SPACING);
                    $y = self::MARGIN_Y + ($row * self::LR_Y_SPACING);
                }

                $node['x'] = $x;
                $node['y'] = $y;
                $node['width'] = $width;
                $node['height'] = $height;
                $node['cx'] = (int) ($x + ($width / 2));
                $node['cy'] = (int) ($y + ($height / 2));
                $node['label_lines'] = $this->labelLines((string) ($node['label'] ?? $nodeId));
                $node['points'] = $this->diamondPoints($x, $y, $width, $height);
                $node['class'] = 'diagram-node-shape-' . ($node['shape'] ?? 'process');

                $positioned[$nodeId] = $node;
                $maxX = max($maxX, $x + $width);
                $maxY = max($maxY, $y + $height);
            }
        }

        $renderedEdges = [];
        foreach ($edges as $edge) {
            if (!isset($positioned[$edge['from']], $positioned[$edge['to']])) {
                continue;
            }

            $renderedEdges[] = $this->edgeGeometry($edge, $positioned[$edge['from']], $positioned[$edge['to']], $orientation);
        }

        return [
            'id' => $id,
            'safe_id' => $this->safeId($id),
            'type' => $format,
            'format' => $format,
            'orientation' => $orientation,
            'source' => $source,
            'is_rendered' => true,
            'nodes' => array_values($positioned),
            'edges' => $renderedEdges,
            'node_count' => count($positioned),
            'transition_count' => count($renderedEdges),
            'svg' => [
                'width' => $maxX + self::MARGIN_X,
                'height' => $maxY + self::MARGIN_Y,
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param list<array{from:string,to:string,label:string,raw:string}> $edges
     * @return array<string,int>
     */
    private function levels(array $nodes, array $edges): array
    {
        $levels = [];
        $incoming = [];

        foreach ($nodes as $id => $_node) {
            $levels[$id] = 0;
            $incoming[$id] = 0;
        }

        foreach ($edges as $edge) {
            if (isset($incoming[$edge['to']])) {
                $incoming[$edge['to']]++;
            }
        }

        foreach ($incoming as $id => $count) {
            if ($count === 0) {
                $levels[$id] = 0;
            }
        }

        $guard = max(1, count($nodes) * 2);
        for ($i = 0; $i < $guard; $i++) {
            $changed = false;
            foreach ($edges as $edge) {
                if (!isset($levels[$edge['from']], $levels[$edge['to']])) {
                    continue;
                }

                $candidate = $levels[$edge['from']] + 1;
                if ($candidate > $levels[$edge['to']] && $candidate <= count($nodes)) {
                    $levels[$edge['to']] = $candidate;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }
        }

        return $levels;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function nodeSize(string $shape): array
    {
        if ($shape === 'decision') {
            return [self::DECISION_WIDTH, self::DECISION_HEIGHT];
        }

        if ($shape === 'terminal') {
            return [self::TERMINAL_WIDTH, self::TERMINAL_HEIGHT];
        }

        return [self::NODE_WIDTH, self::NODE_HEIGHT];
    }

    private function diamondPoints(int $x, int $y, int $width, int $height): string
    {
        $cx = (int) ($x + ($width / 2));
        $cy = (int) ($y + ($height / 2));

        return $cx . ',' . $y . ' ' . ($x + $width) . ',' . $cy . ' ' . $cx . ',' . ($y + $height) . ' ' . $x . ',' . $cy;
    }

    /**
     * @param array{from:string,to:string,label:string,raw:string} $edge
     * @param array<string,mixed> $from
     * @param array<string,mixed> $to
     * @return array<string,mixed>
     */
    private function edgeGeometry(array $edge, array $from, array $to, string $orientation): array
    {
        if ($orientation === 'TD') {
            $x1 = (int) $from['cx'];
            $y1 = (int) ($from['y'] + $from['height']);
            $x2 = (int) $to['cx'];
            $y2 = (int) $to['y'];
            $midY = (int) (($y1 + $y2) / 2);
            $path = 'M ' . $x1 . ' ' . $y1 . ' C ' . $x1 . ' ' . $midY . ', ' . $x2 . ' ' . $midY . ', ' . $x2 . ' ' . $y2;
            $labelX = (int) (($x1 + $x2) / 2) + 16;
            $labelY = $midY - 8;
        } else {
            $x1 = (int) ($from['x'] + $from['width']);
            $y1 = (int) $from['cy'];
            $x2 = (int) $to['x'];
            $y2 = (int) $to['cy'];
            $midX = (int) (($x1 + $x2) / 2);
            $path = 'M ' . $x1 . ' ' . $y1 . ' C ' . $midX . ' ' . $y1 . ', ' . $midX . ' ' . $y2 . ', ' . $x2 . ' ' . $y2;
            $labelX = $midX;
            $labelY = (int) (($y1 + $y2) / 2) - 10;
        }

        return [
            'from' => $edge['from'],
            'to' => $edge['to'],
            'label' => $edge['label'],
            'label_lines' => $this->labelLines($edge['label'], 18),
            'raw' => $edge['raw'],
            'path' => $path,
            'label_x' => $labelX,
            'label_y' => $labelY,
        ];
    }

    /**
     * @return list<string>
     */
    private function labelLines(string $label, int $limit = 20): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $label) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) > $limit && $current !== '') {
                $lines[] = $current;
                $current = $word;
                continue;
            }
            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 3);
    }

    private function safeId(string $id): string
    {
        $safe = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $id) ?? 'diagram');
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : 'diagram';
    }

    /**
     * @return array<string,mixed>
     */
    private function errorModel(string $id, string $type, string $source, string $error, string $format = ''): array
    {
        return [
            'id' => $id,
            'safe_id' => $this->safeId($id),
            'type' => $type,
            'format' => $format,
            'source' => $source,
            'is_rendered' => false,
            'error' => $error,
        ];
    }
}

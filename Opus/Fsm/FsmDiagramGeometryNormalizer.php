<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use RuntimeException;

/**
 * Bounds long FSM SVG transition corridors without changing FSM semantics.
 *
 * OPUS_FSM_Diagram intentionally computes semantic endpoints before drawing.
 * Dense finite-state projections can nevertheless allocate an outer corridor
 * from a high source/target port ordinal. That ordinal is not a geometry unit:
 * multiplying it without a bound can place a path outside the SVG viewBox.
 *
 * This component preserves the renderer endpoints and transition identity,
 * but rewrites only `outer-forward` / `outer-return` paths into deterministic
 * orthogonal lanes inside the existing viewBox. Transition labels are moved to
 * their normalized lane. The SVG physical width/height may then be scaled while
 * the canonical viewBox and state coordinates stay unchanged.
 */
final class FsmDiagramGeometryNormalizer implements
    FsmDiagramGeometryNormalizerInterface
{
    private const MIN_SCALE = 0.45;
    private const MAX_SCALE = 1.0;
    private const VIEWBOX_TOP_GUARD = 44.0;
    private const VIEWBOX_BOTTOM_GUARD = 28.0;
    private const NODE_LANE_GAP = 18.0;
    private const MIN_LANE_GAP = 7.0;
    private const MAX_LANE_GAP = 14.0;
    private const VIEWPORT_VERTICAL_MARGIN = 22.0;

    public function normalize(string $html, float $scale = 0.60): string
    {
        if ($html === '') {
            throw new InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_HTML_EMPTY'
            );
        }
        if (!is_finite($scale)
            || $scale < self::MIN_SCALE
            || $scale > self::MAX_SCALE) {
            throw new InvalidArgumentException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_SCALE_INVALID'
            );
        }

        [$svgWidth, $svgHeight] = $this->svgDimensions($html);
        $nodes = $this->nodeRectangles($html);
        [$minNodeY, $maxNodeBottom] = $this->nodeVerticalBounds($nodes);
        $groups = $this->outerTransitionGroups($html);

        if ($groups !== []) {
            $laneKeys = ['top' => [], 'bottom' => []];
            $labelOwnerByKey = [];
            foreach ($groups as $index => $group) {
                $geometry = $this->pathGeometry($group['html']);
                $direction = $this->corridorDirection($geometry);
                $label = $this->transitionLabelText($group['html']);
                $targetState = $this->targetStateForEndpoint(
                    $geometry['x2'],
                    $geometry['y2'],
                    $nodes
                );
                $key = $direction . "\0" . $label . "\0" . $targetState;
                $groups[$index]['direction'] = $direction;
                $groups[$index]['lane_key'] = $key;
                $groups[$index]['actionable'] = str_contains(
                    $group['html'],
                    ' actionable'
                );
                $laneKeys[$direction][$key] = true;
                if (!isset($labelOwnerByKey[$key])
                    || $groups[$index]['actionable']) {
                    $labelOwnerByKey[$key] = $index;
                }
            }

            $topGap = $this->laneGap(
                max(0.0, $minNodeY - self::NODE_LANE_GAP
                    - self::VIEWBOX_TOP_GUARD),
                count($laneKeys['top'])
            );
            $bottomGap = $this->laneGap(
                max(0.0, $svgHeight - self::VIEWBOX_BOTTOM_GUARD
                    - ($maxNodeBottom + self::NODE_LANE_GAP)),
                count($laneKeys['bottom'])
            );

            $laneYByKey = [];
            $ordinal = 0;
            foreach (array_keys($laneKeys['top']) as $key) {
                $laneYByKey[$key] = max(
                    self::VIEWBOX_TOP_GUARD,
                    $minNodeY - self::NODE_LANE_GAP - $ordinal * $topGap
                );
                ++$ordinal;
            }
            $ordinal = 0;
            foreach (array_keys($laneKeys['bottom']) as $key) {
                $laneYByKey[$key] = min(
                    $svgHeight - self::VIEWBOX_BOTTOM_GUARD,
                    $maxNodeBottom + self::NODE_LANE_GAP
                        + $ordinal * $bottomGap
                );
                ++$ordinal;
            }

            for ($index = count($groups) - 1; $index >= 0; --$index) {
                $group = $groups[$index];
                $key = $group['lane_key'];
                $replacement = $this->normalizeOuterGroup(
                    $group['html'],
                    $laneYByKey[$key],
                    ($labelOwnerByKey[$key] ?? $index) === $index
                );
                $html = substr($html, 0, $group['start'])
                    . $replacement
                    . substr($html, $group['end']);
            }
        }

        $html = $this->expandSignalLabelBoxes($html, 1.35);
        [$html, $viewportHeight] = $this->compactVerticalViewport(
            $html,
            $svgWidth,
            $svgHeight
        );
        $html = $this->scalePhysicalSvg(
            $html,
            $svgWidth,
            $viewportHeight,
            $scale
        );
        return str_replace(
            'data-opus-fsm-routing="lane-aware-v4-signal-types"',
            'data-opus-fsm-routing="bounded-orthogonal-v6-responsive"',
            $html
        );
    }

    /** @return array{0:float,1:float} */
    private function svgDimensions(string $html): array
    {
        if (preg_match(
            '/<svg\b[^>]*class="fsm-diagram"[^>]*\bwidth="([0-9.]+)"'
                . '[^>]*\bheight="([0-9.]+)"/s',
            $html,
            $match
        ) !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_SVG_DIMENSIONS_MISSING'
            );
        }

        $width = (float) $match[1];
        $height = (float) $match[2];
        if ($width <= 0.0 || $height <= 0.0) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_SVG_DIMENSIONS_INVALID'
            );
        }
        return [$width, $height];
    }

    /**
     * @return list<array{id:string,x:float,y:float,w:float,h:float}>
     */
    private function nodeRectangles(string $html): array
    {
        if (preg_match_all(
            '/<g\b[^>]*class="[^"]*\bfsm-node\b[^"]*"[^>]*'
                . 'data-state="([^"]+)"[^>]*>\s*<rect\b[^>]*'
                . 'x="([0-9.]+)"[^>]*y="([0-9.]+)"[^>]*'
                . 'width="([0-9.]+)"[^>]*height="([0-9.]+)"/s',
            $html,
            $matches,
            PREG_SET_ORDER
        ) < 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_STATE_BOUNDS_MISSING'
            );
        }

        $nodes = [];
        foreach ($matches as $match) {
            $nodes[] = [
                'id' => html_entity_decode(
                    $match[1],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ),
                'x' => (float) $match[2],
                'y' => (float) $match[3],
                'w' => (float) $match[4],
                'h' => (float) $match[5],
            ];
        }
        return $nodes;
    }

    /**
     * @param list<array{id:string,x:float,y:float,w:float,h:float}> $nodes
     * @return array{0:float,1:float}
     */
    private function nodeVerticalBounds(array $nodes): array
    {
        $minY = INF;
        $maxBottom = -INF;
        foreach ($nodes as $node) {
            $minY = min($minY, $node['y']);
            $maxBottom = max($maxBottom, $node['y'] + $node['h']);
        }
        return [$minY, $maxBottom];
    }

    /**
     * @param list<array{id:string,x:float,y:float,w:float,h:float}> $nodes
     */
    private function targetStateForEndpoint(
        float $x,
        float $y,
        array $nodes
    ): string {
        $epsilon = 1.1;
        foreach ($nodes as $node) {
            $insideX = $x >= $node['x'] - $epsilon
                && $x <= $node['x'] + $node['w'] + $epsilon;
            $onHorizontalBoundary = abs($y - $node['y']) <= $epsilon
                || abs($y - ($node['y'] + $node['h'])) <= $epsilon;
            if ($insideX && $onHorizontalBoundary) {
                return $node['id'];
            }
        }
        return $this->number($x) . ':' . $this->number($y);
    }

    /**
     * @return list<array{start:int,end:int,html:string,direction:string,lane_key?:string,actionable?:bool}>
     */
    private function outerTransitionGroups(string $html): array
    {
        $groups = [];
        if (preg_match_all(
            '/<g\b[^>]*class="[^"]*\bouter-(?:forward|return)\b[^"]*"'
                . '[^>]*data-transition-id="[^"]+"[^>]*>/s',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE
        ) < 1) {
            return [];
        }

        foreach ($matches[0] as [$tag, $start]) {
            $openEnd = $start + strlen($tag);
            $end = $this->matchingGroupEnd($html, $openEnd);
            $groups[] = [
                'start' => $start,
                'end' => $end,
                'html' => substr($html, $start, $end - $start),
                'direction' => '',
            ];
        }
        return $groups;
    }

    private function matchingGroupEnd(string $html, int $offset): int
    {
        $depth = 1;
        $cursor = $offset;
        $length = strlen($html);
        while ($cursor < $length && $depth > 0) {
            if (preg_match(
                '/<g\b[^>]*>|<\/g>/s',
                $html,
                $match,
                PREG_OFFSET_CAPTURE,
                $cursor
            ) !== 1) {
                break;
            }
            $token = $match[0][0];
            $position = $match[0][1];
            $cursor = $position + strlen($token);
            if (str_starts_with($token, '</g')) {
                --$depth;
            } else {
                ++$depth;
            }
        }
        if ($depth !== 0) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_GROUP_UNBALANCED'
            );
        }
        return $cursor;
    }

    /**
     * @return array{x1:float,y1:float,x2:float,y2:float,corridor:float}
     */
    private function pathGeometry(string $group): array
    {
        if (preg_match(
            '/<path\b[^>]*class="fsm-edge"[^>]*\sd="([^"]+)"/s',
            $group,
            $match
        ) !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_EDGE_PATH_MISSING'
            );
        }
        preg_match_all('/-?[0-9]+(?:\.[0-9]+)?/', $match[1], $numbers);
        $values = array_map('floatval', $numbers[0]);
        if (count($values) < 8) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_EDGE_PATH_INVALID'
            );
        }

        $x1 = $values[0];
        $y1 = $values[1];
        $x2 = $values[count($values) - 2];
        $y2 = $values[count($values) - 1];
        $ys = [];
        for ($index = 1; $index < count($values); $index += 2) {
            $ys[] = $values[$index];
        }
        $minY = min($ys);
        $maxY = max($ys);
        $topExcursion = min($y1, $y2) - $minY;
        $bottomExcursion = $maxY - max($y1, $y2);
        $corridor = $topExcursion >= $bottomExcursion ? $minY : $maxY;

        return [
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'corridor' => $corridor,
        ];
    }

    /** @param array{x1:float,y1:float,x2:float,y2:float,corridor:float} $geometry */
    private function corridorDirection(array $geometry): string
    {
        return $geometry['corridor'] < min($geometry['y1'], $geometry['y2'])
            ? 'top'
            : 'bottom';
    }

    private function transitionLabelText(string $group): string
    {
        if (preg_match(
            '/<text\b[^>]*class="fsm-edge-label"[^>]*>(.*?)<\/text>/s',
            $group,
            $match
        ) !== 1) {
            return '';
        }
        return html_entity_decode(
            strip_tags($match[1]),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function laneGap(float $available, int $count): float
    {
        if ($count <= 1) {
            return 0.0;
        }
        return min(
            self::MAX_LANE_GAP,
            max(self::MIN_LANE_GAP, $available / ($count - 1))
        );
    }

    private function normalizeOuterGroup(
        string $group,
        float $laneY,
        bool $showLabel
    ): string
    {
        $geometry = $this->pathGeometry($group);
        $x1 = $geometry['x1'];
        $y1 = $geometry['y1'];
        $x2 = $geometry['x2'];
        $y2 = $geometry['y2'];
        $path = 'M' . $this->number($x1) . ' ' . $this->number($y1)
            . ' L' . $this->number($x1) . ' ' . $this->number($laneY)
            . ' L' . $this->number($x2) . ' ' . $this->number($laneY)
            . ' L' . $this->number($x2) . ' ' . $this->number($y2);

        $group = preg_replace(
            '/(<path\b[^>]*class="fsm-edge"[^>]*\sd=")[^"]+("[^>]*>)/s',
            '$1' . $path . '$2',
            $group,
            1
        ) ?? $group;

        /* A normalized label sits on its own horizontal lane. */
        $labelY = $laneY - 8.0;
        $group = preg_replace(
            '/<path\b[^>]*class="fsm-label-leader"[^>]*\/>/s',
            '',
            $group
        ) ?? $group;
        $group = preg_replace_callback(
            '/(<rect\b[^>]*class="fsm-edge-label-bg"[^>]*\by=")'
                . '(-?[0-9.]+)(")/s',
            fn (array $match): string => $match[1]
                . $this->number($labelY - 14.0) . $match[3],
            $group,
            1
        ) ?? $group;
        $group = preg_replace_callback(
            '/(<text\b[^>]*class="fsm-edge-label"[^>]*\by=")'
                . '(-?[0-9.]+)(")/s',
            fn (array $match): string => $match[1]
                . $this->number($labelY) . $match[3],
            $group,
            1
        ) ?? $group;

        if (!$showLabel) {
            $group = preg_replace(
                '/<g\b([^>]*)class="fsm-edge-label-box"/',
                '<g$1class="fsm-edge-label-box" style="display:none"',
                $group,
                1
            ) ?? $group;
        }

        return $group;
    }


    private function expandSignalLabelBoxes(string $html, float $factor): string
    {
        return preg_replace_callback(
            '/<rect\b[^>]*class="fsm-edge-label-bg"[^>]*>/s',
            function (array $match) use ($factor): string {
                $tag = $match[0];
                if (preg_match('/\sx="(-?[0-9.]+)"/', $tag, $xMatch) !== 1
                    || preg_match('/\swidth="([0-9.]+)"/', $tag, $wMatch) !== 1
                    || preg_match('/\sy="(-?[0-9.]+)"/', $tag, $yMatch) !== 1
                    || preg_match('/\sheight="([0-9.]+)"/', $tag, $hMatch) !== 1) {
                    return $tag;
                }

                $x = (float) $xMatch[1];
                $width = (float) $wMatch[1];
                $y = (float) $yMatch[1];
                $height = (float) $hMatch[1];
                $newWidth = $width * $factor;
                $newHeight = max(24.0, $height);
                $newX = $x - ($newWidth - $width) / 2.0;
                $newY = $y - ($newHeight - $height) / 2.0;

                $tag = preg_replace(
                    '/\sx="-?[0-9.]+"/',
                    ' x="' . $this->number($newX) . '"',
                    $tag,
                    1
                ) ?? $tag;
                $tag = preg_replace(
                    '/\sy="-?[0-9.]+"/',
                    ' y="' . $this->number($newY) . '"',
                    $tag,
                    1
                ) ?? $tag;
                $tag = preg_replace(
                    '/\swidth="[0-9.]+"/',
                    ' width="' . $this->number($newWidth) . '"',
                    $tag,
                    1
                ) ?? $tag;
                return preg_replace(
                    '/\sheight="[0-9.]+"/',
                    ' height="' . $this->number($newHeight) . '"',
                    $tag,
                    1
                ) ?? $tag;
            },
            $html
        ) ?? $html;
    }

    /**
     * Crops unused vertical viewBox space after geometry normalization.
     *
     * The semantic x coordinates and width remain untouched. Vertical bounds
     * are derived from rendered state rectangles, edge paths and signal label
     * boxes, then padded with one deterministic safety margin. Hidden SVG
     * title/subtitle/legend elements are intentionally excluded from the
     * visual envelope.
     *
     * @return array{0:string,1:float}
     */
    private function compactVerticalViewport(
        string $html,
        float $svgWidth,
        float $svgHeight
    ): array {
        $minY = INF;
        $maxY = -INF;

        foreach ($this->nodeRectangles($html) as $node) {
            $minY = min($minY, $node['y']);
            $maxY = max($maxY, $node['y'] + $node['h']);
        }

        if (preg_match_all(
            '/<path\\b[^>]*class="fsm-edge"[^>]*\\sd="([^"]+)"/s',
            $html,
            $pathMatches,
            PREG_SET_ORDER
        ) > 0) {
            foreach ($pathMatches as $match) {
                preg_match_all(
                    '/-?[0-9]+(?:\\.[0-9]+)?/',
                    $match[1],
                    $numbers
                );
                $values = array_map('floatval', $numbers[0]);
                for ($index = 1; $index < count($values); $index += 2) {
                    $minY = min($minY, $values[$index]);
                    $maxY = max($maxY, $values[$index]);
                }
            }
        }

        if (preg_match_all(
            '/<rect\\b[^>]*class="fsm-edge-label-bg"[^>]*>/s',
            $html,
            $labelMatches
        ) > 0) {
            foreach ($labelMatches[0] as $tag) {
                if (preg_match('/\\sy="(-?[0-9.]+)"/', $tag, $yMatch) !== 1
                    || preg_match('/\\sheight="([0-9.]+)"/', $tag, $hMatch) !== 1) {
                    continue;
                }
                $y = (float) $yMatch[1];
                $height = (float) $hMatch[1];
                $minY = min($minY, $y);
                $maxY = max($maxY, $y + $height);
            }
        }

        if (!is_finite($minY) || !is_finite($maxY) || $maxY <= $minY) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_VERTICAL_BOUNDS_INVALID'
            );
        }

        $viewY = max(0.0, $minY - self::VIEWPORT_VERTICAL_MARGIN);
        $viewBottom = min(
            $svgHeight,
            $maxY + self::VIEWPORT_VERTICAL_MARGIN
        );
        $viewHeight = max(1.0, $viewBottom - $viewY);

        $pattern = '/(<svg\\b[^>]*class="fsm-diagram"[^>]*\\bviewBox=")'
            . '-?[0-9.]+\\s+-?[0-9.]+\\s+[0-9.]+\\s+[0-9.]+(")/s';
        $result = preg_replace_callback(
            $pattern,
            fn (array $match): string => $match[1]
                . '0 '
                . $this->number($viewY)
                . ' '
                . $this->number($svgWidth)
                . ' '
                . $this->number($viewHeight)
                . $match[2],
            $html,
            1,
            $count
        );
        if (!is_string($result) || $count !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_VIEWBOX_COMPACT_FAILED'
            );
        }

        return [$result, $viewHeight];
    }

    private function scalePhysicalSvg(
        string $html,
        float $width,
        float $height,
        float $scale
    ): string {
        $scaledWidth = $this->number($width * $scale);
        $scaledHeight = $this->number($height * $scale);
        $pattern = '/(<svg\b[^>]*class="fsm-diagram"[^>]*\bwidth=")'
            . '[0-9.]+("[^>]*\bheight=")[0-9.]+(")/s';
        $result = preg_replace_callback(
            $pattern,
            static fn (array $match): string => $match[1]
                . $scaledWidth . $match[2] . $scaledHeight . $match[3],
            $html,
            1,
            $count
        );
        if (!is_string($result) || $count !== 1) {
            throw new RuntimeException(
                'OPUS_FSM_DIAGRAM_GEOMETRY_SVG_SCALE_FAILED'
            );
        }
        return $result;
    }

    private function number(float $value): string
    {
        return rtrim(
            rtrim(number_format($value, 2, '.', ''), '0'),
            '.'
        );
    }
}

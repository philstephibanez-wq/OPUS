<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Template\ScoreTemplateRenderer;

/** Builds the filtered view-model consumed by the generic SCORE profiler. */
final class WebProfilerView implements WebProfilerViewInterface
{
    public function renderTrace(array $trace): string
    {
        $events = $this->normalizeEvents((array) ($trace['events'] ?? []));
        $spans = $this->normalizeSpans((array) ($trace['spans'] ?? []));
        $panels = $this->buildPanels($events, $spans);
        $statusCounts = is_array($trace['status_counts'] ?? null) ? $trace['status_counts'] : [];

        return (new ScoreTemplateRenderer(__DIR__ . '/templates/web_profiler'))->render('layout.score', [
            'title' => 'OPUS Profiler',
            'trace' => [
                'trace_id' => (string) ($trace['trace_id'] ?? ''),
                'started_at' => (string) ($trace['started_at'] ?? ''),
                'duration_ms' => (string) ($trace['duration_ms'] ?? ''),
                'event_count' => (string) count($events),
                'span_count' => (string) count($spans),
                'success_count' => (string) ($statusCounts['success'] ?? 0),
                'warning_count' => (string) ($statusCounts['warning'] ?? 0),
                'error_count' => (string) ($statusCounts['error'] ?? 0),
                'unavailable_count' => (string) ($statusCounts['unavailable'] ?? 0),
            ],
            'panels' => $panels,
        ]);
    }

    /** @param list<array<string,mixed>> $events @param list<array<string,mixed>> $spans @return list<array<string,mixed>> */
    private function buildPanels(array $events, array $spans): array
    {
        $definitions = [
            'summary' => ['Summary', []], 'timeline' => ['Timeline', ['*']],
            'http' => ['Request / Response', ['http', 'request', 'response']],
            'routing' => ['Routing / Controller', ['routing', 'controller']], 'fsm' => ['FSM', ['fsm']],
            'score' => ['SCORE', ['score', 'template', 'asset']],
            'security' => ['Security / ACL / SSO', ['security', 'acl', 'sso', 'auth']],
            'database' => ['Database', ['database']], 'rest' => ['REST', ['rest', 'rcp']],
            'composer' => ['Composer', ['composer']], 'session' => ['Session', ['session']],
            'cache' => ['Cache', ['cache']], 'i18n' => ['I18n', ['i18n', 'translation', 'locale']],
            'logs' => ['Logs', ['log', 'logger']], 'exceptions' => ['Exceptions', ['exception']],
            'configuration' => ['Configuration', ['config', 'configuration']],
            'runtime' => ['Runtime PHP / OPUS', ['runtime', 'profiler']],
            'performance' => ['Performance', ['memory', 'performance']],
        ];

        $panels = [];
        foreach ($definitions as $id => [$label, $categories]) {
            $rows = [];
            if ($id === 'timeline') {
                $rows = $spans !== [] ? $spans : $events;
            } elseif ($id !== 'summary') {
                foreach (array_merge($spans, $events) as $row) {
                    if (in_array($row['category'], $categories, true)
                        || ($id === 'routing' && in_array(
                            $row['type'],
                            ['http.route.resolved', 'http.controller.selected'],
                            true
                        ))
                    ) {
                        $rows[] = $row;
                    }
                }
            }
            $panels[] = [
                'id' => $id, 'label' => $label, 'count' => (string) count($rows), 'rows' => $rows,
                'rows_available' => $rows !== [], 'summary' => $id === 'summary', 'details' => $id !== 'summary',
            ];
        }
        return $panels;
    }

    /** @param array<int,mixed> $events @return list<array<string,mixed>> */
    private function normalizeEvents(array $events): array
    {
        $rows = [];
        foreach ($events as $event) {
            if (!is_array($event)) { continue; }
            $rows[] = $this->buildRow(
                'Événement', (string) ($event['index'] ?? ''), (string) ($event['elapsed_ms'] ?? ''),
                (string) ($event['category'] ?? ''),
                (string) ($event['type'] ?? (($event['category'] ?? '') . '.' . ($event['name'] ?? ''))),
                (string) ($event['status'] ?? 'unavailable'), (string) ($event['span_id'] ?? ''),
                (string) ($event['parent_span_id'] ?? ''), (array) ($event['context'] ?? [])
            );
        }
        return $rows;
    }

    /** @param array<int,mixed> $spans @return list<array<string,mixed>> */
    private function normalizeSpans(array $spans): array
    {
        $rows = [];
        foreach ($spans as $span) {
            if (!is_array($span)) { continue; }
            $category = (string) ($span['category'] ?? '');
            $rows[] = $this->buildRow(
                'Étape', '', (string) ($span['duration_ms'] ?? ''), $category,
                $category . '.' . (string) ($span['name'] ?? ''),
                (string) ($span['status'] ?? 'unavailable'), (string) ($span['span_id'] ?? ''),
                (string) ($span['parent_span_id'] ?? ''), (array) ($span['context'] ?? [])
            );
        }
        return $rows;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function buildRow(string $kind, string $index, string $elapsed, string $category, string $type, string $status, string $spanId, string $parentSpanId, array $context): array
    {
        if ($category === 'fsm') {
            unset($context['fsm_contract']);
        }
        $fields = $this->flattenContext($context);
        return [
            'kind' => $kind, 'index' => $index, 'elapsed_ms' => $elapsed, 'category' => $category,
            'type' => $type, 'status' => $status, 'span_id' => $spanId, 'parent_span_id' => $parentSpanId,
            'context_summary' => match (true) {
                $category === 'fsm' => $this->fsmSummary($context),
                $type === 'http.route.resolved' => $this->routeSummary($context),
                $type === 'http.controller.selected' => $this->controllerSummary($context),
                default => $this->contextSummary($fields),
            },
            'context_fields' => $fields,
            'context_available' => $fields !== [], 'context_json' => $this->encode($context),
        ];
    }

    /** @param array<string,mixed> $context */
    private function fsmSummary(array $context): string
    {
        $table = trim((string) ($context['table_fsm'] ?? ''));
        $current = trim((string) ($context['current_state'] ?? ''));
        $signal = trim((string) ($context['signal'] ?? ''));
        $next = trim((string) ($context['next_state'] ?? ''));

        if ($table === '' || $current === '' || $signal === '') {
            return $this->contextSummary($this->flattenContext($context));
        }

        return $table . ' · ' . $current . ' + ' . $signal . ' → '
            . ($next !== '' ? $next : 'transition_not_found');
    }

    /** @param array<string,mixed> $context */
    private function routeSummary(array $context): string
    {
        $route = trim((string) ($context['normalized_route'] ?? ''));
        $source = trim((string) ($context['rule_source'] ?? ''));
        if ($route === '' || $source === '') {
            return $this->contextSummary($this->flattenContext($context));
        }

        return $route . ' · règle ' . $source;
    }

    /** @param array<string,mixed> $context */
    private function controllerSummary(array $context): string
    {
        $class = trim((string) ($context['controller_class'] ?? ''));
        $action = trim((string) ($context['controller_action'] ?? ''));
        $route = trim((string) ($context['normalized_route'] ?? ''));
        if ($class === '' || $action === '') {
            return $this->contextSummary($this->flattenContext($context));
        }

        return $class . '::' . $action . ($route !== '' ? ' · ' . $route : '');
    }

    /** @param array<string,mixed> $context @return list<array<string,string>> */
    private function flattenContext(array $context): array
    {
        $fields = [];
        $walk = static function (mixed $value, string $path) use (&$walk, &$fields): void {
            if (is_array($value)) {
                if ($value === []) {
                    $fields[] = ['path' => $path, 'type' => 'array', 'value' => '[]'];
                    return;
                }
                foreach ($value as $key => $child) {
                    $walk($child, $path === '' ? (string) $key : $path . '.' . $key);
                }
                return;
            }
            $type = get_debug_type($value);
            if (is_bool($value)) { $display = $value ? 'true' : 'false'; }
            elseif ($value === null) { $display = 'null'; }
            elseif (is_scalar($value)) { $display = (string) $value; }
            else { $display = '[' . $type . ']'; }
            $fields[] = ['path' => $path === '' ? '(value)' : $path, 'type' => $type, 'value' => $display];
        };
        $walk($context, '');
        return $fields;
    }

    /** @param list<array<string,string>> $fields */
    private function contextSummary(array $fields): string
    {
        if ($fields === []) { return 'Aucun détail'; }
        $parts = [];
        foreach (array_slice($fields, 0, 3) as $field) {
            $value = $field['value'];
            if (strlen($value) > 48) { $value = substr($value, 0, 45) . '…'; }
            $parts[] = $field['path'] . '=' . $value;
        }
        if (count($fields) > 3) { $parts[] = '+' . (count($fields) - 3) . ' champs'; }
        return implode(' · ', $parts);
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}

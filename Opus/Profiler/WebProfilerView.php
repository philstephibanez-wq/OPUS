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
        $statusCounts = is_array($trace['status_counts'] ?? null)
            ? $trace['status_counts']
            : [];

        return (new ScoreTemplateRenderer(__DIR__ . '/templates/web_profiler'))->render(
            'layout.score',
            [
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
                'events' => $events,
                'events_available' => $events !== [],
                'spans' => $spans,
                'spans_available' => $spans !== [],
                'panels' => $panels,
            ]
        );
    }

    /**
     * Builds the fixed profiler navigation without claiming unobserved activity.
     *
     * @param list<array<string,string>> $events
     * @param list<array<string,string>> $spans
     * @return list<array<string,mixed>>
     */
    private function buildPanels(array $events, array $spans): array
    {
        $definitions = [
            'summary' => ['Summary', []],
            'timeline' => ['Timeline', ['*']],
            'http' => ['Request / Response', ['http', 'request', 'response']],
            'routing' => ['Routing / Controller', ['routing', 'controller']],
            'fsm' => ['FSM', ['fsm']],
            'score' => ['SCORE', ['score', 'template', 'asset']],
            'security' => ['Security / ACL / SSO', ['security', 'acl', 'sso', 'auth']],
            'database' => ['Database', ['database']],
            'rest' => ['REST', ['rest', 'rcp']],
            'composer' => ['Composer', ['composer']],
            'session' => ['Session', ['session']],
            'cache' => ['Cache', ['cache']],
            'i18n' => ['I18n', ['i18n', 'translation', 'locale']],
            'logs' => ['Logs', ['log', 'logger']],
            'exceptions' => ['Exceptions', ['exception']],
            'configuration' => ['Configuration', ['config', 'configuration']],
            'runtime' => ['Runtime PHP / OPUS', ['runtime', 'profiler']],
            'performance' => ['Performance', ['memory', 'performance']],
        ];

        $panels = [];
        foreach ($definitions as $id => [$label, $categories]) {
            $rows = [];
            if ($id === 'timeline') {
                $rows = array_merge($spans, $events);
            } elseif ($id !== 'summary') {
                foreach (array_merge($spans, $events) as $row) {
                    if (in_array($row['category'], $categories, true)) {
                        $rows[] = $row;
                    }
                }
            }
            $panels[] = [
                'id' => $id,
                'label' => $label,
                'count' => (string) count($rows),
                'rows' => $rows,
                'rows_available' => $rows !== [],
                'summary' => $id === 'summary',
                'details' => $id !== 'summary',
            ];
        }

        return $panels;
    }

    /** @param array<int,mixed> $events @return list<array<string,string>> */
    private function normalizeEvents(array $events): array
    {
        $rows = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $rows[] = [
                'kind' => 'Event',
                'index' => (string) ($event['index'] ?? ''),
                'elapsed_ms' => (string) ($event['elapsed_ms'] ?? ''),
                'category' => (string) ($event['category'] ?? ''),
                'type' => (string) ($event['type'] ?? (
                    ($event['category'] ?? '') . '.' . ($event['name'] ?? '')
                )),
                'status' => (string) ($event['status'] ?? 'unavailable'),
                'span_id' => (string) ($event['span_id'] ?? ''),
                'parent_span_id' => (string) ($event['parent_span_id'] ?? ''),
                'context_json' => $this->encode((array) ($event['context'] ?? [])),
            ];
        }
        return $rows;
    }

    /** @param array<int,mixed> $spans @return list<array<string,string>> */
    private function normalizeSpans(array $spans): array
    {
        $rows = [];
        foreach ($spans as $span) {
            if (!is_array($span)) {
                continue;
            }
            $rows[] = [
                'kind' => 'Span',
                'index' => '',
                'elapsed_ms' => (string) ($span['duration_ms'] ?? ''),
                'category' => (string) ($span['category'] ?? ''),
                'type' => (string) (($span['category'] ?? '') . '.' . ($span['name'] ?? '')),
                'span_id' => (string) ($span['span_id'] ?? ''),
                'parent_span_id' => (string) ($span['parent_span_id'] ?? ''),
                'operation' => (string) (
                    ($span['category'] ?? '') . '.' . ($span['name'] ?? '')
                ),
                'status' => (string) ($span['status'] ?? 'unavailable'),
                'duration_ms' => (string) ($span['duration_ms'] ?? ''),
                'context_json' => $this->encode((array) ($span['context'] ?? [])),
            ];
        }
        return $rows;
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }
}

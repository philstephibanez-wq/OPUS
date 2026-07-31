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
            ]
        );
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
                'index' => (string) ($event['index'] ?? ''),
                'elapsed_ms' => (string) ($event['elapsed_ms'] ?? ''),
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

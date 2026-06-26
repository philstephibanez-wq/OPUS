<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

final class MemoryCollector implements MemoryCollectorInterface
{
    public function category(): string
    {
        return 'memory';
    }

    public function label(): string
    {
        return 'Memory';
    }

    public function collect(array $trace): array
    {
        $events = (array)($trace['events'] ?? []);
        $rows = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            if (isset($event['memory'])) {
                $rows[] = [
                    'index' => (string)($event['index'] ?? ''),
                    'time' => (string)($event['time'] ?? ''),
                    'elapsed_ms' => (string)($event['elapsed_ms'] ?? ''),
                    'category' => (string)($event['category'] ?? ''),
                    'name' => (string)($event['name'] ?? ''),
                    'context_json' => json_encode($event['context'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ];
            }
        }
        return $rows;
    }
}
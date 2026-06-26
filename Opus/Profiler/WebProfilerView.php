<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Profiler\Collector\ConfigCollector;
use Opus\Profiler\Collector\DatabaseCollector;
use Opus\Profiler\Collector\ExceptionCollector;
use Opus\Profiler\Collector\MailCollector;
use Opus\Profiler\Collector\MemoryCollector;
use Opus\Profiler\Collector\ProfilerCollectorInterface;
use Opus\Profiler\Collector\RequestCollector;
use Opus\Profiler\Collector\RoutingCollector;
use Opus\Profiler\Collector\RuntimeCollector;
use Opus\Profiler\Collector\TemplateCollector;
use Opus\Template\ScoreTemplateRenderer;

final class WebProfilerView implements WebProfilerViewInterface
{
    public function renderIndex(array $traces, array $fsmMaps): string
    {
        return $this->render('layout.score', [
            'title' => 'OPUS Web Profiler',
            'mode' => 'index',
            'has_trace' => false,
            'traces' => $traces,
            'trace' => [],
            'panels' => [],
            'fsm_maps' => $this->normalizeFsmMaps($fsmMaps),
        ]);
    }

    public function renderTrace(array $trace, array $fsmMaps): string
    {
        $panels = [];
        foreach ($this->collectors() as $collector) {
            $events = $collector->collect($trace);
            $panels[] = [
                'id' => $collector->category(),
                'label' => $collector->label(),
                'count' => count($events),
                'events' => $events,
            ];
        }
        return $this->render('layout.score', [
            'title' => 'OPUS Web Profiler · ' . (string)($trace['trace_id'] ?? ''),
            'mode' => 'trace',
            'has_trace' => true,
            'traces' => [],
            'trace' => [
                'trace_id' => (string)($trace['trace_id'] ?? ''),
                'started_at' => (string)($trace['started_at'] ?? ''),
                'duration_ms' => (string)($trace['duration_ms'] ?? ''),
                'event_count' => (string)($trace['event_count'] ?? count((array)($trace['events'] ?? []))),
            ],
            'panels' => $panels,
            'fsm_maps' => $this->normalizeFsmMaps($fsmMaps),
        ]);
    }

    private function render(string $template, array $data): string
    {
        $this->requireScoreRuntime();
        $renderer = new ScoreTemplateRenderer(__DIR__ . '/templates/web_profiler');
        return $renderer->render($template, $data);
    }

    private function requireScoreRuntime(): void
    {
        require_once __DIR__ . '/../Score/TemplateException.php';
        require_once __DIR__ . '/../Score/TemplateRendererInterface.php';
        require_once __DIR__ . '/../Score/ScoreTemplateRenderer.php';
    }

    /** @return list<ProfilerCollectorInterface> */
    private function collectors(): array
    {
        $base = __DIR__ . '/Collector';
        foreach ([
            'ProfilerCollectorInterface.php', 'RequestCollectorInterface.php', 'RequestCollector.php',
            'RoutingCollectorInterface.php', 'RoutingCollector.php',
            'ExceptionCollectorInterface.php', 'ExceptionCollector.php',
            'TemplateCollectorInterface.php', 'TemplateCollector.php',
            'DatabaseCollectorInterface.php', 'DatabaseCollector.php',
            'ConfigCollectorInterface.php', 'ConfigCollector.php',
            'MailCollectorInterface.php', 'MailCollector.php',
            'MemoryCollectorInterface.php', 'MemoryCollector.php',
            'RuntimeCollectorInterface.php', 'RuntimeCollector.php',
        ] as $file) {
            require_once $base . '/' . $file;
        }
        return [
            new RequestCollector(),
            new RoutingCollector(),
            new ExceptionCollector(),
            new TemplateCollector(),
            new DatabaseCollector(),
            new ConfigCollector(),
            new MailCollector(),
            new MemoryCollector(),
            new RuntimeCollector(),
        ];
    }

    private function normalizeFsmMaps(array $fsmMaps): array
    {
        $rows = [];
        foreach ($fsmMaps as $id) {
            $rows[] = ['id' => (string)$id];
        }
        return $rows;
    }
}
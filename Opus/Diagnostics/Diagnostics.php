<?php
declare(strict_types=1);

namespace Opus\Diagnostics;

use Opus\Log\Logger;
use Opus\Profiler\Profiler;

/**
 * Official OPUS diagnostics helper.
 *
 * Contract:
 * - replaces the removed legacy debug helper;
 * - keeps temporary legacy-style HTML rendering while old UI paths are migrated;
 * - writes through the official Logger when diagnostics are enabled;
 * - emits Profiler events when a profiler is configured;
 * - contains no project-specific business logic.
 */
final class Diagnostics
 implements DiagnosticsInterface {
    /** @var array<int,object> */
    private static array $logs = [];
    private static string $root = '../';
    private static bool $enabled = false;
    private static ?Logger $logger = null;
    private static ?Profiler $profiler = null;
    private static ?string $traceId = null;
    private static string $channel = 'diagnostics';

    private function __construct()
    {
    }

    public static function configure(bool $enabled = true, string $root = '../../logs'): void
    {
        self::$enabled = $enabled;
        self::$root = $root;

        if ($enabled) {
            self::$logger = new Logger($root);
        }
    }

    public static function configureLogger(Logger $logger, ?string $traceId = null, string $channel = 'diagnostics'): void
    {
        self::$logger = $logger;
        self::$traceId = $traceId;
        self::$channel = $channel;
    }

    public static function configureProfiler(?Profiler $profiler): void
    {
        self::$profiler = $profiler;
    }

    public static function clear(): void
    {
        self::$logs = [];
        self::$logger = null;
        self::$profiler = null;
        self::$traceId = null;
        self::$channel = 'diagnostics';
        self::$enabled = false;
        self::$root = '../';
    }

    public static function debug($msg, $script, $line, $color = 'black', $logIt = false): void
    {
        if (!self::$enabled) {
            return;
        }

        $message = self::stringify($msg);
        self::appendLegacyHtmlLog('<pre>' . $message . '</pre>', (string) $script, (int) $line, (string) $color);

        self::bridge('debug.message', $message, (string) $script, (int) $line, [
            'kind' => 'message',
            'color' => (string) $color,
        ]);

        if ($logIt) {
            self::writeLegacyFile($message, (string) $script, (int) $line);
        }
    }

    public static function dump($objName, $obj, $script, $line, $color = 'black', $logIt = false): void
    {
        if (!self::$enabled) {
            return;
        }

        $objStr = print_r($obj, true);
        $count = is_countable($obj) ? count($obj) : 1;

        self::appendLegacyHtmlLog(
            '<h3>' . (string) $objName . '</h3> Count: ' . $count . '<pre>' . $objStr . '</pre>',
            (string) $script,
            (int) $line,
            (string) $color
        );

        self::bridge('debug.dump', (string) $objName, (string) $script, (int) $line, [
            'kind' => 'dump',
            'object_name' => (string) $objName,
            'count' => $count,
            'color' => (string) $color,
        ]);

        if ($logIt) {
            self::writeLegacyFile('OBJECT: ' . (string) $objName . PHP_EOL . $objStr, (string) $script, (int) $line);
        }
    }

    public static function dumpClasses($script, $line, $color = 'black', $logIt = false): void
    {
        self::dump('DECLARED CLASSES', get_declared_classes(), $script, $line, $color, $logIt);
    }

    public static function renderLegacyHtml(): string
    {
        if (!self::$enabled || count(self::$logs) === 0) {
            return '';
        }

        $totalTime = 0.0;
        $subTime = 0.0;
        $logs = "<ul class='debug' id='debug'>";
        $startTime = self::$logs[0]->time;
        $lastTime = $startTime;
        $lastScript = self::$logs[0]->script;

        for ($index = 0; $index < count(self::$logs); $index++) {
            if ($lastScript !== self::$logs[$index]->script) {
                $lastScript = self::$logs[$index]->script;
                $logs .= '<li>SUB TIME: ' . number_format($subTime, 4) . '</li>';
                $subTime = 0.0;
            }

            $dt = self::$logs[$index]->time - $lastTime;
            $lastTime = self::$logs[$index]->time;

            $logs .= "<li class='debug'><span class='debug' id='debug'>" . ($index + 1) . ': ';
            $logs .= ' Script: ' . self::$logs[$index]->script . ' Line: ' . self::$logs[$index]->line . '</span>';
            $logs .= ' Time: ' . number_format($dt, 4);
            $logs .= ' Memory: ' . intval(memory_get_usage() / 1000) . 'K';
            $logs .= "<br/><span style='color:" . self::$logs[$index]->color . ";'>" . self::$logs[$index]->msg . '</span>';
            $logs .= '</li>';

            $totalTime += $dt;
            $subTime += $dt;
        }

        $logs .= '<li>TOTAL TIME: ' . number_format($totalTime, 4) . ' Memory: ' . intval(memory_get_usage() / 1000) . 'K</li>';
        $logs .= '</ul>';

        self::$logs = [];

        return $logs;
    }

    private static function appendLegacyHtmlLog(string $htmlMessage, string $script, int $line, string $color): void
    {
        $item = new \stdClass();
        $item->msg = $htmlMessage;
        $item->script = basename($script);
        $item->line = $line;
        $item->color = $color;
        $item->time = microtime(true);

        self::$logs[] = $item;
    }

    /** @param array<string,mixed> $context */
    private static function bridge(string $name, string $message, string $script, int $line, array $context): void
    {
        $context['script'] = basename($script);
        $context['line'] = $line;

        if (self::$logger !== null) {
            self::$logger->debug(self::$channel, $message, $context, self::$traceId);
        }

        if (self::$profiler !== null) {
            self::$profiler->event('diagnostics', $name, $context + [
                'message' => $message,
            ]);
        }
    }

    private static function writeLegacyFile(string $message, string $script, int $line): void
    {
        $path = rtrim(self::$root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($script) . '.log';
        $lineText = date("\nd.m.Y h:i:s") . " Line: $line | " . $message;

        if (file_put_contents($path, $lineText, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('OPUS_DIAGNOSTICS_LEGACY_LOG_WRITE_FAILED: ' . $path);
        }
    }

    private static function stringify($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            return $json;
        }

        return print_r($value, true);
    }
}

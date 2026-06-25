<?php

#[AllowDynamicProperties]
/**
 * Legacy OPUS debug helper.
 *
 * P7A0E contract:
 * - keeps the historical OPUS_Debug API alive;
 * - keeps legacy in-memory HTML debug output;
 * - can bridge debug events to the official Logger and Profiler;
 * - does not require callers to be migrated immediately;
 * - must not be deleted while active OPUS_Debug calls exist.
 */
abstract class OPUS_Debug
{
    public static $_logs = array();
    public static $_root = '../';
    public static $_debug = false;

    private static $_logger = null;
    private static $_profiler = null;
    private static ?string $_traceId = null;
    private static string $_channel = 'legacy_debug';

    public static function setDebug($debug = true, $root = '../../logs')
    {
        self::$_debug = $debug;
        self::$_root = $root;
    }

    public static function setLogger($logger, ?string $traceId = null, string $channel = 'legacy_debug'): void
    {
        if (!is_object($logger) || !method_exists($logger, 'debug')) {
            throw new InvalidArgumentException('OPUS_DEBUG_LOGGER_CONTRACT_INVALID');
        }

        self::$_logger = $logger;
        self::$_traceId = $traceId;
        self::$_channel = $channel;
    }

    public static function setProfiler($profiler): void
    {
        if (!is_object($profiler) || !method_exists($profiler, 'event')) {
            throw new InvalidArgumentException('OPUS_DEBUG_PROFILER_CONTRACT_INVALID');
        }

        self::$_profiler = $profiler;
    }

    public static function clearBridge(): void
    {
        self::$_logger = null;
        self::$_profiler = null;
        self::$_traceId = null;
        self::$_channel = 'legacy_debug';
    }

    public static function get()
    {
        if (self::$_debug) {
            if (count(self::$_logs) === 0) {
                return '';
            }

            $totaltime = 0;
            $sub_time = 0;
            $logs = "<ul class='debug' id='debug'>";
            $start_time = self::$_logs[0]->time;
            $last_time = $start_time;
            $last_script = self::$_logs[0]->script;

            for ($l = 0; $l < count(self::$_logs); $l++) {
                if ($last_script != self::$_logs[$l]->script) {
                    $last_script = self::$_logs[$l]->script;
                    $logs .= '<li>SUB TIME: ' . number_format($sub_time, 4) . '</li>';
                    $sub_time = 0;
                }

                $logs .= "<li class='debug'><span class='debug' id='debug'>" . ($l + 1) . ': ';
                $dt = number_format(self::$_logs[$l]->time - $last_time, 4);
                $last_time = self::$_logs[$l]->time;
                $logs .= ' Script: ' . self::$_logs[$l]->script . ' Line: ' . self::$_logs[$l]->line . '</span>';
                $logs .= ' Time: ' . $dt;
                $logs .= ' Memory: ' . intval(memory_get_usage() / 1000) . 'K';
                $logs .= "<br/><span style='color:" . self::$_logs[$l]->color . ";'>" . self::$_logs[$l]->msg . '</span>';
                $logs .= '</li>';
                $totaltime += $dt;
                $sub_time += $dt;
            }

            $logs .= '<li>TOTAL TIME: ' . number_format($totaltime, 4) . ' Memory: ' . intval(memory_get_usage() / 1000) . 'K</li>';
            $logs .= '</ul>';

            self::$_logs = array();

            return $logs;
        }

        return '';
    }

    public static function add($msg, $script, $line, $color = 'black', $logIt = false)
    {
        if (self::$_debug) {
            $newItem = new stdClass();
            $newItem->msg = '<pre>' . self::stringify($msg) . '</pre>';
            $newItem->script = basename((string) $script);
            $newItem->line = $line;
            $newItem->color = $color;
            $newItem->time = microtime(true);
            self::$_logs[] = $newItem;

            self::bridge('debug.add', self::stringify($msg), (string) $script, (int) $line, [
                'kind' => 'message',
                'color' => (string) $color,
            ]);

            if ($logIt) {
                error_log(date("\nd.m.Y h:i:s") . " Line: $line | " . self::stringify($msg), 3, self::$_root . '/' . basename((string) $script) . '.log');
            }
        }
    }

    public static function addDump($objName, $obj, $script, $line, $color = 'black', $logIt = false)
    {
        if (self::$_debug) {
            $newItem = new stdClass();
            $objStr = print_r($obj, true);
            $count = is_countable($obj) ? count($obj) : 1;

            $newItem->msg = "<h3>$objName</h3> Count: " . $count . '<pre>' . $objStr . '</pre>';
            $newItem->script = basename((string) $script);
            $newItem->line = $line;
            $newItem->color = $color;
            $newItem->time = microtime(true);
            self::$_logs[] = $newItem;

            self::bridge('debug.dump', (string) $objName, (string) $script, (int) $line, [
                'kind' => 'dump',
                'object_name' => (string) $objName,
                'count' => $count,
                'color' => (string) $color,
            ]);

            if ($logIt) {
                error_log(date("\nd.m.Y h:i:s") . " Line: $line | OBJECT: $objName \n$objStr", 3, self::$_root . '/' . basename((string) $script) . '.log');
            }
        }
    }

    public static function addClasses($script, $line, $color = 'black', $logIt = false)
    {
        if (self::$_debug) {
            $classes = get_declared_classes();
            self::addDump('DECLARED CLASSES', $classes, $script, $line, $color, $logIt);
        }
    }

    private static function bridge(string $name, string $message, string $script, int $line, array $context): void
    {
        $context['script'] = basename($script);
        $context['line'] = $line;

        if (self::$_logger !== null) {
            self::$_logger->debug(self::$_channel, $message, $context, self::$_traceId);
        }

        if (self::$_profiler !== null) {
            self::$_profiler->event('legacy_debug', $name, $context + [
                'message' => $message,
            ]);
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
} // class

?>

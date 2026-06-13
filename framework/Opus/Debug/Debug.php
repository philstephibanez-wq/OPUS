<?php

declare(strict_types=1);

namespace Opus\Debug;

/*
 * OPUS_REFBOOK:
 *   domain: DEBUG
 *   role: Class Debug belongs to the DEBUG Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DEBUG domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - debug-overview
 *   diagrams:
 *     - debug-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED DEBUG UTILITY
 *
 * Role:
 *   Preserve the original Opus Debug helper as a controlled diagnostic utility.
 *
 * Responsibility:
 *   Format and collect diagnostic values without performing output side effects.
 *
 * Contract:
 *   Debug never echoes by itself. Caller decides representation/output.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112P1 restores add/addClasses/addDump/get/setDebug.
 */
final class Debug
{
    private static bool $enabled = true;

    /** @var array<int,array{type:string,value:mixed}> */
    private static array $entries = [];

    /**
     * @param mixed $value Value to inspect.
     */
    public static function dump(mixed $value): string
    {
        return print_r($value, true);
    }

    public static function setDebug(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function add(mixed $value): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$entries[] = [
            'type' => 'value',
            'value' => $value,
        ];
    }

    /**
     * @param string|string[] $classes Classes to store.
     */
    public static function addClasses(string|array $classes): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$entries[] = [
            'type' => 'classes',
            'value' => array_values((array) $classes),
        ];
    }

    public static function addDump(mixed $value): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$entries[] = [
            'type' => 'dump',
            'value' => self::dump($value),
        ];
    }

    /**
     * @return array<int,array{type:string,value:mixed}>
     */
    public static function get(): array
    {
        return self::$entries;
    }
}

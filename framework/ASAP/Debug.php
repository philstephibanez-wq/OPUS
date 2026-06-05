<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED DEBUG UTILITY
 *
 * Role:
 *   Preserve the original ASAP Debug helper as a controlled diagnostic utility.
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

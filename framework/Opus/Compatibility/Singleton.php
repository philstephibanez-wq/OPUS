<?php

declare(strict_types=1);

namespace Opus\Compatibility;

/*
 * OPUS_REFBOOK:
 *   domain: COMPATIBILITY
 *   role: Class Singleton belongs to the COMPATIBILITY Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the COMPATIBILITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - compatibility-overview
 *   diagrams:
 *     - compatibility-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the original singleton helper for legacy code that still depends on it.
 *
 * Contract:
 *   One instance per concrete class. No service locator and no hidden dependencies.
 *
 * Since:
 *   P112O
 */
class Singleton
{
    /** @var array<class-string,object> */
    private static array $instances = [];

    protected function __construct()
    {
    }

    final public static function getInstance(): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        /** @var static $instance */
        $instance = self::$instances[$class];

        return $instance;
    }

    /**
     * @param array<int,mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        throw \ASAP\Exception\Exception::because('OPUS_SINGLETON_METHOD_NOT_FOUND', static::class . '::' . $name);
    }
}

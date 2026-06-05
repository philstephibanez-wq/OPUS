<?php

declare(strict_types=1);

namespace ASAP;

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
        throw Exception::because('ASAP_SINGLETON_METHOD_NOT_FOUND', static::class . '::' . $name);
    }
}

<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Explicit allow-list registry for pure destination-assignment hooks.
 */
final class LstsarTransformHookRegistry implements LstsarTransformHookRegistryInterface
{
    /** @var array<string,LstsarTransformHookInterface> */
    private array $hooks = [];

    /** @param list<LstsarTransformHookInterface> $hooks */
    public function __construct(array $hooks = [])
    {
        foreach ($hooks as $hook) {
            $this->register($hook);
        }
    }

    public static function empty(): self
    {
        return new self();
    }

    public function register(LstsarTransformHookInterface $hook): self
    {
        $name = trim($hook->name());
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,120}$/', $name)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_TRANSFORM_HOOK_NAME_INVALID: ' . $name);
        }
        $this->hooks[$name] = $hook;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->hooks[$name]);
    }

    public function compute(string $name, LstsarTransformHookContext $context): mixed
    {
        if (!$this->has($name)) {
            throw new \RuntimeException('OPUS_LSTSAR_TRANSFORM_HOOK_MISSING: ' . $name);
        }

        return $this->hooks[$name]->compute($context);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->hooks);
    }
}

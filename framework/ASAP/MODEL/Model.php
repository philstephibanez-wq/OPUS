<?php

declare(strict_types=1);

namespace ASAP\MODEL;

/**
 * PUBLIC LEGACY-ALIGNED MODEL
 *
 * Role:
 *   Preserve the original ASAP `MODEL\Model` concept.
 *
 * Responsibility:
 *   Carry validated model attributes.
 *
 * Contract:
 *   Model carries data only. It does not render and does not access storage.
 *
 * Since:
 *   P112D4C
 */
class Model
{
    /** @var array<string,mixed> */
    private array $attributes = [];

    /**
     * @param array<string,mixed> $attributes Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function get(string $name): mixed
    {
        if (!array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException('ASAP_MODEL_ATTRIBUTE_MISSING: ' . $name);
        }

        return $this->attributes[$name];
    }

    public function set(string $name, mixed $value): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('ASAP_MODEL_ATTRIBUTE_EMPTY');
        }

        $this->attributes[$name] = $value;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }
}

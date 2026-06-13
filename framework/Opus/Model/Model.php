<?php

declare(strict_types=1);

namespace Opus\Model;

/*
 * OPUS_REFBOOK:
 *   domain: MODEL
 *   role: Class Model belongs to the MODEL Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the MODEL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - model-overview
 *   diagrams:
 *     - model-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED MODEL
 *
 * Role:
 *   Preserve the original Opus `MODEL\Model` concept.
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
            throw new \InvalidArgumentException('OPUS_MODEL_ATTRIBUTE_MISSING: ' . $name);
        }

        return $this->attributes[$name];
    }

    public function set(string $name, mixed $value): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('OPUS_MODEL_ATTRIBUTE_EMPTY');
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

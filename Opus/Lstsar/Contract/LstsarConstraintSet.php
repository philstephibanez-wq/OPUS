<?php
declare(strict_types=1);

namespace Opus\Lstsar\Contract;

/**
 * Immutable source/target constraint set for LSTSAR declarations.
 *
 * This object models declared constraints only. Enforcement will be added by later
 * LSTSAR validation services; no silent fallback is allowed when a key is invalid.
 */
final class LstsarConstraintSet implements LstsarConstraintSetInterface
{
    /** @var array<string,mixed> */
    private array $constraints;

    /** @var list<string> */
    private array $allowedKeys = [
        'type',
        'min_length',
        'max_length',
        'exact_length',
        'max_bytes',
        'precision',
        'scale',
        'format',
        'schema',
    ];

    /**
     * @param array<string,mixed> $constraints
     */
    private function __construct(array $constraints)
    {
        foreach ($constraints as $key => $value) {
            if (!in_array((string) $key, $this->allowedKeys, true)) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_CONSTRAINT_KEY_UNKNOWN: ' . $key);
            }
            if ($value === null || $value === '') {
                throw new \InvalidArgumentException('OPUS_LSTSAR_CONSTRAINT_VALUE_EMPTY: ' . $key);
            }
        }

        $this->constraints = $constraints;
    }

    /**
     * @param array<string,mixed> $constraints
     */
    public static function fromArray(array $constraints): self
    {
        return new self($constraints);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->constraints;
    }
}

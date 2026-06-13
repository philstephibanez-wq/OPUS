<?php

declare(strict_types=1);

namespace Opus\Recipe;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the result of one recipe execution.
 *
 * Responsibility:
 *   Preserve recipe name, status, duration, emitted markers and diagnostics.
 *
 * Contract:
 *   Result data is append-only after construction and can be safely serialized
 *   into recipe reports.
 */
final class RecipeResult
{
    /**
     * @param string[] $markers Console markers emitted by the recipe.
     * @param string[] $diagnostics Diagnostic messages and warnings.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly float $durationSeconds,
        public readonly array $markers = [],
        public readonly array $diagnostics = []
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'duration_seconds' => $this->durationSeconds,
            'markers' => $this->markers,
            'diagnostics' => $this->diagnostics,
        ];
    }
}

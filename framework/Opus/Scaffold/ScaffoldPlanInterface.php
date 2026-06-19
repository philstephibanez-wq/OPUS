<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Contract for OPUS scaffold plans.
 */
interface ScaffoldPlanInterface
{
    public function rootRelativePath(): string;

    /**
     * @return list<ScaffoldEntry>
     */
    public function entries(): array;
}

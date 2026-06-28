<?php
declare(strict_types=1);

namespace Opus\Lstsar\Stage;

/**
 * Base contract for a LSTSAR stage.
 */
interface LstsarStageInterface
{
    public function stageName(): string;

    /**
     * @return array<string,mixed>
     */
    public function contract(): array;
}

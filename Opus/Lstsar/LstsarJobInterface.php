<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Contract for a declared LSTSAR processing job.
 *
 * A job identifies a declared source, expected constraints, security context and
 * payload metadata before data enters the transform/store path.
 */
interface LstsarJobInterface
{
    public function id(): string;

    public function pipelineId(): string;

    /**
     * @return array<string,mixed>
     */
    public function sourceContract(): array;

    /**
     * @return array<string,mixed>
     */
    public function constraints(): array;

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array;
}

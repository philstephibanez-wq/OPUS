<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Contract for a LSTSAR execution report.
 *
 * Reports are explicit outputs and may be rendered as JSON, HTML, logs or documentation
 * through representation-specific adapters. They are not raw side effects.
 */
interface LstsarReportInterface
{
    public function id(): string;

    public function jobId(): string;

    public function status(): string;

    /**
     * @return list<array<string,mixed>>
     */
    public function auditTrail(): array;

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array;
}

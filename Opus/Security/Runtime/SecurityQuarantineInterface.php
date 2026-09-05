<?php
declare(strict_types=1);

namespace Opus\Security\Runtime;

interface SecurityQuarantineInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * Persist a security quarantine if none is active.
     *
     * Repeated activation is idempotent and preserves the original incident.
     *
     * @return array<string,string>
     */
    public function quarantine(string $reasonCode): array;

    /**
     * Existing malformed quarantine artifacts still count as quarantined.
     */
    public function isQuarantined(): bool;

    /**
     * Read and validate the durable quarantine state.
     *
     * @return array<string,string>
     */
    public function state(): array;

    /**
     * Fail closed while a quarantine artifact exists.
     */
    public function assertBusinessAllowed(): void;
}

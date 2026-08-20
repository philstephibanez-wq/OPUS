<?php
declare(strict_types=1);

interface BackendBackendApiControllerInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function matchesCurrentRequest(): bool;
    public function run(): void;
}

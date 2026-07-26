<?php
declare(strict_types=1);

namespace Opus\Console\Development;

interface DevelopmentServerRegistryInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function register(string $applicationId, string $host, int $port): array;

    public function unregister(string $applicationId): void;

    public function endpoint(string $applicationId, string $path): string;
}

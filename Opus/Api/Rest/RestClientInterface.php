<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

use Opus\Profiler\ProfilerInterface;

interface RestClientInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function fromConfig(
        string $configFile,
        ?ProfilerInterface $profiler = null
    ): self;

    /**
     * @param array<string,mixed> $body
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function request(
        string $method,
        string $resource,
        array $body,
        array $actor
    ): array;
}

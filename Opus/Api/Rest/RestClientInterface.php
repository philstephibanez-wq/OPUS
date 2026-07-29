<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

interface RestClientInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
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

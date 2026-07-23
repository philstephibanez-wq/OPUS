<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

interface RcpRestClientInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $parameters @param array<string,mixed> $actor @return array<string,mixed> */
    public function execute(string $operation, array $parameters, array $actor): array;
}

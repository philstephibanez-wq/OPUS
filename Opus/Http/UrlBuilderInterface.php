<?php
declare(strict_types=1);

namespace Opus\Http;

interface UrlBuilderInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param list<string> $segments @param array<string,scalar|null> $query */
    public function build(array $segments, array $query = []): string;

    /** @param array<string,scalar|null> $query */
    public function withQuery(string $url, array $query): string;
}

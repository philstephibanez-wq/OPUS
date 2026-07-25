<?php
declare(strict_types=1);

namespace Opus\Application\Runtime;

use Opus\Http\Response;

interface LayeredGeneratedSiteRuntimeInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function handle(): Response;

    public function failureResponse(\Throwable $error, string $traceId): Response;

    public function mode(): string;
}

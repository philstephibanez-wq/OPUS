<?php
declare(strict_types=1);

namespace Opus\Http;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;
use Opus\Profiler\ProfilerInterface;

/**
 * Resolves stable canonical application routes to localized public paths
 * and back without translating resource tails or backend REST endpoints.
 */
interface LocalizedRouteResolverInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /**
     * @param list<string> $supportedLocales
     */
    public static function fromFile(
        string $configFile,
        array $supportedLocales,
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): LocalizedRouteResolverInterface;

    public function resolve(string $locale, string $publicPath): string;

    public function localize(string $locale, string $canonicalPath): string;

    /**
     * @param array<string,scalar|null> $query
     */
    public function url(
        string $basePath,
        string $locale,
        string $canonicalPath,
        array $query = []
    ): string;

    public function isLocalized(string $locale, string $publicPath): bool;
}

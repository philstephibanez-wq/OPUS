<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for canonicalizing generated-site entries to the environment Profiler policy. */
interface ProfilerEnvironmentScaffoldPolicyInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public function normalizeDirectory(string $relativePath): string;

    /** @return array{0:string,1:string} */
    public function normalizeFile(string $relativePath, string $content): array;
}

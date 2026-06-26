<?php
declare(strict_types=1);

namespace Opus\Fsm\Runtime;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

interface FsmRuntimeConfigLoaderInterface extends OpusFrameworkComponentInterface, OpusExceptionAwareInterface, OpusProfilerAwareInterface, OpusSelfDocumentingInterface
{
    public function load(string $id): array;
    public function availableMaps(): array;
    public function flowForDisplay(string $id): array;
}
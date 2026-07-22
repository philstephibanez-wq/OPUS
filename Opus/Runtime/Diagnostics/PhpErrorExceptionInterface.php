<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusExceptionContractInterface;

interface PhpErrorExceptionInterface extends OpusExceptionContractInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
}
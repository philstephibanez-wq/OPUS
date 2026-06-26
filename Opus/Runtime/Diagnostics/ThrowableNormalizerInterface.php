<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusFrameworkComponentInterface;

interface ThrowableNormalizerInterface extends OpusFrameworkComponentInterface
{
    public static function normalize(\Throwable $throwable): array;
}
<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusFrameworkComponentInterface;

interface PhpErrorInterceptorInterface extends OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function register(string $rootDir): void;
}
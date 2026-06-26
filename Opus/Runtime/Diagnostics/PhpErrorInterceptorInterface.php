<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

use Opus\Framework\OpusFrameworkComponentInterface;

interface PhpErrorInterceptorInterface extends OpusFrameworkComponentInterface
{
    public static function register(string $rootDir): void;
}
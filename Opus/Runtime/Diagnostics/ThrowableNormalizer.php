<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class ThrowableNormalizer implements ThrowableNormalizerInterface
{
    public static function normalize(\Throwable $throwable): array
    {
        return [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => array_slice($throwable->getTrace(), 0, 20),
        ];
    }
}
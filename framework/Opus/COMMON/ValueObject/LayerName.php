<?php
declare(strict_types=1);

namespace Opus\COMMON\ValueObject;

final class LayerName
{
    public const FRONT = 'FRONT';
    public const MIDDLE = 'MIDDLE';
    public const BACK = 'BACK';
    public const COMMON = 'COMMON';

    private function __construct()
    {
    }
}

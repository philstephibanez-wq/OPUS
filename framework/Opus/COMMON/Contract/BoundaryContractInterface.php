<?php
declare(strict_types=1);

namespace Opus\COMMON\Contract;

/**
 * Layer boundary contract marker.
 *
 * Implementations describe a cross-layer contract without owning the
 * processing itself. Boundary contracts are allowed in COMMON because they
 * are shared language, not FRONT/MIDDLE/BACK behavior.
 */
interface BoundaryContractInterface
{
    public function contractId(): string;

    public function sourceLayer(): string;

    public function targetLayer(): string;
}

<?php

declare(strict_types=1);

namespace ASAP\RefBook\Contract;

/**
 * PUBLIC RefBook opt-in contract.
 *
 * Role:
 *   Marks a class as officially intended for RefBook inspection without asking
 *   the developer to manually duplicate method signatures.
 *
 * Contract:
 *   - Reflection remains the source of truth for class and method signatures;
 *   - this interface exposes only stable classification data;
 *   - method lists must never be maintained manually here.
 */
interface RefBookInspectableInterface
{
    /**
     * PUBLIC RefBook domain provider.
     *
     * @return string Stable functional domain used by snapshot/API consumers.
     */
    public static function refBookDomain(): string;
}

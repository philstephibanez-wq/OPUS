<?php

declare(strict_types=1);

namespace Opus\RefBook\Contract;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Interface RefBookInspectableInterface belongs to the REFBOOK Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REFBOOK domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - refbook-overview
 *   diagrams:
 *     - refbook-runtime
 * END_OPUS_REFBOOK
 */
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

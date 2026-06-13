<?php

declare(strict_types=1);

namespace Opus\Contract;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: CONTRACT
 *   role: Class ContractException belongs to the CONTRACT Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONTRACT domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - contract-overview
 *   diagrams:
 *     - contract-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent an explicit Opus contract failure.
 *
 * Responsibility:
 *   Carry a clear failure code and message when a runtime boundary is invalid.
 *
 * Contract:
 *   No silent fallback. Every invalid boundary must throw an explicit exception.
 *
 * Since:
 *   P112D1
 */
final class ContractException extends RuntimeException
{
    /**
     * PUBLIC FACTORY
     *
     * @param string $codeName Stable contract error code.
     * @param string $detail Optional diagnostic detail.
     *
     * @return self Explicit contract exception.
     */
    public static function because(string $codeName, string $detail = ''): self
    {
        $message = trim($detail) === '' ? $codeName : $codeName . ': ' . $detail;

        return new self($message);
    }
}

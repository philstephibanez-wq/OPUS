<?php
declare(strict_types=1);

namespace Opus\Contract;

use RuntimeException;

/**
 * Exception raised when an OPUS framework contract is violated.
 *
 * The message always starts with the explicit OPUS diagnostic code so smokes,
 * logs and callers can assert deterministic failures.
 */
final class ContractException extends RuntimeException implements ContractExceptionInterface
{
    /**
     * Create a contract exception from an explicit diagnostic code.
     */
    public static function because(string $code, string $detail = ''): self
    {
        $message = $code;
        if ($detail !== '') {
            $message .= ': ' . $detail;
        }

        return new self($message);
    }
}

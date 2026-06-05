<?php

declare(strict_types=1);

namespace ASAP\MAIL;

/**
 * PUBLIC LEGACY-ALIGNED MAIL MESSAGE
 *
 * Role:
 *   Preserve the original ASAP `MAIL\Mail` domain.
 *
 * Responsibility:
 *   Carry one email message declaration.
 *
 * Contract:
 *   Data only. Sending belongs to an explicit transport.
 *
 * Since:
 *   P112D4C
 */
final class Mail
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body
    ) {
        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('ASAP_MAIL_TO_INVALID');
        }
    }
}

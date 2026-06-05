<?php

declare(strict_types=1);

namespace ASAP\SMTP;

/**
 * PUBLIC LEGACY-ALIGNED SMTP CONFIG
 *
 * Role:
 *   Preserve the original ASAP `SMTP\Smtp` domain.
 *
 * Responsibility:
 *   Carry explicit SMTP endpoint configuration.
 *
 * Contract:
 *   Data only. Sending belongs to a mail transport.
 *
 * Since:
 *   P112D4C
 */
final class Smtp
{
    public function __construct(
        public readonly string $host,
        public readonly int $port
    ) {
        if (trim($this->host) === '' || $this->port <= 0) {
            throw new \InvalidArgumentException('ASAP_SMTP_CONFIGURATION_INVALID');
        }
    }
}

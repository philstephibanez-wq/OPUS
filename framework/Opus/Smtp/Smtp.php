<?php

declare(strict_types=1);

namespace Opus\Smtp;

/*
 * OPUS_REFBOOK:
 *   domain: SMTP
 *   role: Class Smtp belongs to the SMTP Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the SMTP domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - smtp-overview
 *   diagrams:
 *     - smtp-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED SMTP CONFIG
 *
 * Role:
 *   Preserve the original Opus `SMTP\Smtp` domain.
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
            throw new \InvalidArgumentException('OPUS_SMTP_CONFIGURATION_INVALID');
        }
    }
}

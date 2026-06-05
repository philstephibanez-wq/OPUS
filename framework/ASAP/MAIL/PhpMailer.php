<?php

declare(strict_types=1);

namespace ASAP\MAIL;

/**
 * PUBLIC LEGACY-ALIGNED PHPMAILER BOUNDARY
 *
 * Role:
 *   Preserve the original ASAP `MAIL\PhpMailer` adapter name.
 *
 * Responsibility:
 *   Make external mail runtime dependency explicit.
 *
 * Contract:
 *   Fails clearly until PHPMailer runtime is wired contractually.
 *
 * Since:
 *   P112D4C
 */
final class PhpMailer
{
    public function send(Mail $mail): void
    {
        throw new \RuntimeException('ASAP_PHPMAILER_RUNTIME_NOT_CONFIGURED');
    }
}

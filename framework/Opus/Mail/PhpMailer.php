<?php

declare(strict_types=1);

namespace Opus\Mail;

/*
 * OPUS_REFBOOK:
 *   domain: MAIL
 *   role: Class PhpMailer belongs to the MAIL Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the MAIL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - mail-overview
 *   diagrams:
 *     - mail-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED PHPMAILER BOUNDARY
 *
 * Role:
 *   Preserve the original Opus `MAIL\PhpMailer` adapter name.
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
        throw new \RuntimeException('OPUS_PHPMAILER_RUNTIME_NOT_CONFIGURED');
    }
}

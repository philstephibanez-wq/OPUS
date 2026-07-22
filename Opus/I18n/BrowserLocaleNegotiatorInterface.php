<?php
declare(strict_types=1);

namespace Opus\I18n;

/**
 * Contract interface for Opus\I18n\BrowserLocaleNegotiator.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface BrowserLocaleNegotiatorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param list<string> $supportedLocales */
    public static function forLocales(array $supportedLocales, string $defaultLocale): self;

    public function negotiate(?string $acceptLanguage): Locale;

    public function match(?string $requestedLocale): ?Locale;
}

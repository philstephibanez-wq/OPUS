<?php
declare(strict_types=1);

namespace Opus\I18n;

/**
 * Contract interface for Opus\I18n\Locale.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface LocaleInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function __toString(): string;

    public function parent(): ?self;

    /** @return list<self> */
    public function fallbackChain(): array;
}

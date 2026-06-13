<?php

declare(strict_types=1);

namespace Opus\RefBook\I18n;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookDocumentationTranslationMissingException belongs to the REFBOOK Opus framework domain.
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
 * PUBLIC missing translation error.
 *
 * Role:
 *   Stop localized documentation export when an Opus source text is not translated.
 *
 * Contract:
 *   - missing translations are explicit;
 *   - the source path and source text are preserved for recipes;
 *   - no English fallback is returned for non-English documentation.
 */
final class RefBookDocumentationTranslationMissingException extends RuntimeException
{
    public static function forSourceText(string $language, string $path, string $sourceText): self
    {
        return new self(
            'OPUS_REFBOOK_DOC_TRANSLATION_MISSING'
            . ' lang=' . $language
            . ' path=' . $path
            . ' source=' . $sourceText
        );
    }
}

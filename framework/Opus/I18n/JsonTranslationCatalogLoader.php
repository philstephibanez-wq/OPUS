<?php

declare(strict_types=1);

namespace Opus\I18n;

use JsonException;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class JsonTranslationCatalogLoader belongs to the I18N Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the I18N domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - i18n-overview
 *   diagrams:
 *     - i18n-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LOADER
 *
 * Role:
 *   Load Opus translation catalogs from JSON resources.
 *
 * Responsibility:
 *   Validate the catalog schema and build a typed TranslationCatalog.
 *
 * Contract:
 *   No implicit locale. No implicit messages. Malformed catalogs fail clearly.
 *
 * Since:
 *   P112D4A
 */
final class JsonTranslationCatalogLoader
{
    /**
     * PUBLIC API
     *
     * @param string $file Catalog JSON file.
     *
     * @return TranslationCatalog Loaded catalog.
     */
    public function load(string $file): TranslationCatalog
    {
        if (!is_file($file)) {
            throw TranslationException::because('OPUS_I18N_CATALOG_FILE_MISSING', $file);
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TranslationException::because('OPUS_I18N_CATALOG_JSON_INVALID', $exception->getMessage());
        }

        if (!is_array($data)) {
            throw TranslationException::because('OPUS_I18N_CATALOG_ROOT_INVALID', $file);
        }

        $locale = new LocaleCode((string) ($data['locale'] ?? ''));
        $messages = $data['messages'] ?? [];
        $plurals = $data['plurals'] ?? [];

        if (!is_array($messages) || !is_array($plurals)) {
            throw TranslationException::because('OPUS_I18N_CATALOG_SCHEMA_INVALID', $file);
        }

        return new TranslationCatalog($locale, $messages, $plurals);
    }
}

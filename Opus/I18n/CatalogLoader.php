<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\File\File;
use Opus\File\FileInterface;
use Opus\File\StructuredFileLoader;
use Opus\File\StructuredFileLoaderInterface;

/**
 * Loads canonical locale catalogs through the OPUS File boundary.
 *
 * The catalog filename is strictly <locale>.<extension>, for example:
 * - fr.json;
 * - fr-BE.json;
 * - de-CH.yaml;
 * - uk-UA.xml.
 */
final class CatalogLoader implements CatalogLoaderInterface
{
    public const CONTRACT = 'OPUS_I18N_CATALOG_LOADER_V4';

    private readonly FileInterface $file;
    private readonly StructuredFileLoaderInterface $structured;

    public function __construct(
        ?FileInterface $file = null,
        ?StructuredFileLoaderInterface $structured = null
    ) {
        $this->file = $file ?? File::instance();
        $this->structured = $structured ?? StructuredFileLoader::instance();
    }

    public function loadDirectory(
        string $directory,
        Locale $locale,
        string $scope,
        bool $required
    ): ?Catalog {
        if (!is_dir($directory)) {
            if ($required) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_DIRECTORY_MISSING',
                    $directory
                );
            }

            return null;
        }

        $merged = [];
        $loaded = [];

        foreach ($locale->fallbackChain() as $candidateLocale) {
            $candidates = $this->candidateFiles(
                $directory,
                $candidateLocale
            );

            if (count($candidates) > 1) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_FILE_AMBIGUOUS',
                    implode(',', $candidates)
                );
            }

            if ($candidates === []) {
                continue;
            }

            $catalog = $this->loadFile(
                $candidates[0],
                $candidateLocale,
                $scope
            );
            $merged = array_replace($merged, $catalog->all());
            $loaded[] = $candidates[0];
        }

        if ($loaded === []) {
            if ($required) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_FILE_MISSING',
                    $scope . ':' . $locale
                );
            }

            return null;
        }

        return new Catalog($locale, $scope, $merged);
    }

    public function loadFile(
        string $file,
        Locale $expectedLocale,
        string $scope
    ): Catalog {
        $data = $this->structured->read($file);
        $declaredLocale = (string) (
            $data['locale']
            ?? $data['_locale']
            ?? $expectedLocale->value
        );
        $locale = new Locale($declaredLocale);

        if ($locale->value !== $expectedLocale->value) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_LOCALE_MISMATCH',
                $file . ':' . $locale . ':' . $expectedLocale
            );
        }

        $declaredScope = trim((string) ($data['scope'] ?? $scope));

        if ($declaredScope !== $scope) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_SCOPE_MISMATCH',
                $file . ':' . $declaredScope . ':' . $scope
            );
        }

        if (is_array($data['messages'] ?? null)) {
            $messages = $data['messages'];

            foreach ((array) ($data['plurals'] ?? []) as $key => $forms) {
                if (!is_array($forms)) {
                    throw TranslationException::because(
                        'OPUS_I18N_PLURAL_ENTRY_INVALID',
                        (string) $key
                    );
                }

                $messages[(string) $key] = ['forms' => $forms];
            }

            foreach (
                (array) (
                    $data['grammatical']
                    ?? $data['grammars']
                    ?? []
                ) as $key => $forms
            ) {
                if (!is_array($forms)) {
                    throw TranslationException::because(
                        'OPUS_I18N_GRAMMATICAL_ENTRY_INVALID',
                        (string) $key
                    );
                }

                $messages[(string) $key] = ['forms' => $forms];
            }
        } else {
            $messages = $data;
            unset(
                $messages['contract'],
                $messages['locale'],
                $messages['_locale'],
                $messages['scope']
            );
        }

        return new Catalog($locale, $scope, $messages);
    }

    /** @return list<string> */
    private function candidateFiles(
        string $directory,
        Locale $locale
    ): array {
        $paths = [];

        foreach (['json', 'yaml', 'yml', 'xml'] as $extension) {
            $path = $directory
                . DIRECTORY_SEPARATOR
                . $locale->value
                . '.'
                . $extension;

            if ($this->file->exists($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}

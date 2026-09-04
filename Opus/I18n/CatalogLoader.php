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
 * Regional catalogs are deterministic overlays:
 * - an exact regional file is loaded when present;
 * - its explicit `inherits` chain is resolved in the same scope;
 * - when an optional regional module file is absent, the active locale's
 *   base-language catalog is used as the parent presentation catalog;
 * - inherited messages are merged first and the child overlay wins.
 */
final class CatalogLoader implements CatalogLoaderInterface
{
    public const CONTRACT = 'OPUS_I18N_CATALOG_LOADER_V5';

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

        $exact = $this->singleCandidate(
            $directory,
            $locale
        );

        if ($exact !== null) {
            return $this->loadComposedFile(
                $exact,
                $locale,
                $scope,
                $locale,
                []
            );
        }

        if ($locale->language !== $locale->value) {
            $baseLocale = new Locale($locale->language);
            $base = $this->singleCandidate(
                $directory,
                $baseLocale
            );

            if ($base !== null) {
                return $this->loadComposedFile(
                    $base,
                    $baseLocale,
                    $scope,
                    $locale,
                    []
                );
            }
        }

        if ($required) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_FILE_MISSING',
                $scope . ':' . $locale
            );
        }

        return null;
    }

    public function loadFile(
        string $file,
        Locale $expectedLocale,
        string $scope
    ): Catalog {
        return $this->loadComposedFile(
            $file,
            $expectedLocale,
            $scope,
            $expectedLocale,
            []
        );
    }

    /**
     * @param list<string> $inheritancePath
     */
    private function loadComposedFile(
        string $file,
        Locale $sourceLocale,
        string $scope,
        Locale $activeLocale,
        array $inheritancePath
    ): Catalog {
        $canonicalFile = realpath($file);
        $cycleKey = $canonicalFile !== false
            ? str_replace('\\', '/', $canonicalFile)
            : str_replace('\\', '/', $file);

        if (in_array($cycleKey, $inheritancePath, true)) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_INHERITANCE_CYCLE',
                implode(' -> ', [...$inheritancePath, $cycleKey])
            );
        }

        $data = $this->structured->read($file);
        $declaredLocale = (string) (
            $data['locale']
            ?? $data['_locale']
            ?? $sourceLocale->value
        );
        $catalogLocale = new Locale($declaredLocale);

        if ($catalogLocale->value !== $sourceLocale->value) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_LOCALE_MISMATCH',
                $file . ':' . $catalogLocale . ':' . $sourceLocale
            );
        }

        $declaredScope = trim((string) ($data['scope'] ?? $scope));

        if ($declaredScope !== $scope) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_SCOPE_MISMATCH',
                $file . ':' . $declaredScope . ':' . $scope
            );
        }

        $messages = $this->messagesFromData($data, $scope);
        $inherits = trim((string) ($data['inherits'] ?? ''));

        if ($inherits !== '') {
            $parentLocale = new Locale($inherits);

            if ($parentLocale->language !== $sourceLocale->language) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_INHERITED_LANGUAGE_MISMATCH',
                    $sourceLocale . ':' . $parentLocale
                );
            }

            $parentFile = $this->singleCandidate(
                dirname($file),
                $parentLocale
            );

            if ($parentFile === null) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_INHERITED_FILE_MISSING',
                    $scope . ':' . $parentLocale
                );
            }

            $parent = $this->loadComposedFile(
                $parentFile,
                $parentLocale,
                $scope,
                $activeLocale,
                [...$inheritancePath, $cycleKey]
            );
            $messages = array_replace(
                $parent->all(),
                $messages
            );
        }

        return new Catalog(
            $activeLocale,
            $scope,
            $messages
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string|array<mixed>>
     */
    private function messagesFromData(
        array $data,
        string $scope
    ): array {
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

            return $messages;
        }

        $messages = $data;
        unset(
            $messages['contract'],
            $messages['locale'],
            $messages['_locale'],
            $messages['scope'],
            $messages['inherits'],
            $messages['plurals'],
            $messages['grammatical'],
            $messages['grammars']
        );

        return $messages;
    }

    private function singleCandidate(
        string $directory,
        Locale $locale
    ): ?string {
        $candidates = $this->candidateFiles(
            $directory,
            $locale
        );

        if (count($candidates) > 1) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_FILE_AMBIGUOUS',
                implode(',', $candidates)
            );
        }

        return $candidates[0] ?? null;
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

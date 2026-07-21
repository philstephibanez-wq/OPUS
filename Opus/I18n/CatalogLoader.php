<?php
declare(strict_types=1);

namespace Opus\I18n;

use JsonException;

final class CatalogLoader
{
    public const CONTRACT = 'OPUS_I18N_CATALOG_LOADER_V2';

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

        $candidates = [
            $directory . DIRECTORY_SEPARATOR . $locale->value . '.php',
            $directory . DIRECTORY_SEPARATOR . $locale->value . '.json',
            $directory . DIRECTORY_SEPARATOR
                . 'asap.' . $locale->value . '.json',
        ];
        $existing = array_values(array_filter($candidates, 'is_file'));

        if ($existing === []) {
            if ($required) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_FILE_MISSING',
                    $scope . ':' . $locale
                );
            }

            return null;
        }

        if (count($existing) !== 1) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_FILE_AMBIGUOUS',
                implode(',', $existing)
            );
        }

        return $this->loadFile($existing[0], $locale, $scope);
    }

    public function loadFile(
        string $file,
        Locale $expectedLocale,
        string $scope
    ): Catalog {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $data = match ($extension) {
            'php' => $this->readPhp($file),
            'json' => $this->readJson($file),
            default => throw TranslationException::because(
                'OPUS_I18N_CATALOG_FORMAT_UNSUPPORTED',
                $file
            ),
        };

        return $this->normalize($data, $expectedLocale, $scope, $file);
    }

    /** @return array<mixed> */
    private function readPhp(string $file): array
    {
        $data = require $file;

        if (!is_array($data)) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_PHP_RETURN_INVALID',
                $file
            );
        }

        return $data;
    }

    /** @return array<mixed> */
    private function readJson(string $file): array
    {
        try {
            $data = json_decode(
                (string) file_get_contents($file),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_JSON_INVALID',
                $file . ':' . $exception->getMessage()
            );
        }

        if (!is_array($data)) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_ROOT_INVALID',
                $file
            );
        }

        return $data;
    }

    /**
     * Supports:
     * - current OPUS PHP flat maps;
     * - OPUS_I18N_CATALOG_V2 documents;
     * - ASAP JSON documents with messages + plurals;
     * - optional grammatical maps.
     *
     * @param array<mixed> $data
     */
    private function normalize(
        array $data,
        Locale $expectedLocale,
        string $scope,
        string $file
    ): Catalog {
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

        if (is_array($data['messages'] ?? null)) {
            $messages = $data['messages'];

            foreach ((array) ($data['plurals'] ?? []) as $key => $forms) {
                if (!is_array($forms)) {
                    throw TranslationException::because(
                        'OPUS_I18N_ASAP_PLURAL_ENTRY_INVALID',
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

        /** @var array<string,string|array<mixed>> $messages */
        return new Catalog($locale, $scope, $messages);
    }
}

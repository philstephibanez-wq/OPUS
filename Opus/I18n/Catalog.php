<?php
declare(strict_types=1);

namespace Opus\I18n;

final readonly class Catalog
{
    public const CONTRACT = 'OPUS_I18N_CATALOG_V2';

    /**
     * @param array<string,string|array<mixed>> $messages
     */
    public function __construct(
        public Locale $locale,
        public string $scope,
        private array $messages
    ) {
        if (
            $scope === ''
            || preg_match('/^[a-z][a-z0-9_-]*$/', $scope) !== 1
        ) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_SCOPE_INVALID',
                $scope
            );
        }

        foreach ($messages as $key => $entry) {
            new I18nKey((string) $key);

            if (!is_string($entry) && !is_array($entry)) {
                throw TranslationException::because(
                    'OPUS_I18N_MESSAGE_ENTRY_INVALID',
                    $scope . ':' . $key
                );
            }
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->messages);
    }

    public function entry(string $key): string|array
    {
        if (!$this->has($key)) {
            throw TranslationException::because(
                'OPUS_I18N_MESSAGE_MISSING',
                $this->locale . ':' . $this->scope . ':' . $key
            );
        }

        return $this->messages[$key];
    }

    /** @return array<string,string|array<mixed>> */
    public function all(): array
    {
        return $this->messages;
    }
}

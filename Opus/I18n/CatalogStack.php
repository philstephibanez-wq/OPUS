<?php
declare(strict_types=1);

namespace Opus\I18n;

final readonly class CatalogStack
{
    public const CONTRACT = 'OPUS_I18N_CATALOG_STACK_V1';

    /** @var list<Catalog> */
    private array $catalogs;

    public function __construct(Catalog ...$catalogs)
    {
        if ($catalogs === []) {
            throw TranslationException::because(
                'OPUS_I18N_CATALOG_STACK_EMPTY'
            );
        }

        $locale = $catalogs[0]->locale->value;

        foreach ($catalogs as $catalog) {
            if ($catalog->locale->value !== $locale) {
                throw TranslationException::because(
                    'OPUS_I18N_CATALOG_STACK_LOCALE_MISMATCH'
                );
            }
        }

        $this->catalogs = $catalogs;
    }

    public function locale(): Locale
    {
        return $this->catalogs[0]->locale;
    }

    public function entry(string $key): string|array
    {
        for ($index = count($this->catalogs) - 1; $index >= 0; $index--) {
            if ($this->catalogs[$index]->has($key)) {
                return $this->catalogs[$index]->entry($key);
            }
        }

        throw TranslationException::because(
            'OPUS_I18N_MESSAGE_MISSING',
            $this->locale() . ':' . $key
        );
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return array_map(
            static fn (Catalog $catalog): string => $catalog->scope,
            $this->catalogs
        );
    }
}

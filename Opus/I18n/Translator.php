<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\I18n\Plural\PluralRuleInterface;

final readonly class Translator implements TranslatorInterface
{
    public const CONTRACT = 'OPUS_I18N_TRANSLATOR_ASAP_COMPAT_V2';

    public function __construct(
        private CatalogStack $catalogs,
        private PluralRuleInterface $pluralRule,
        private MessageSelector $selector = new MessageSelector(),
        private MessageInterpolator $interpolator = new MessageInterpolator()
    ) {
    }

    /**
     * @param array<string,mixed> $parameters
     */
    public function translate(
        string $key,
        array $parameters = [],
        int|float|null $count = null,
        Gender|string|null $gender = null
    ): string {
        $key = (new I18nKey($key))->value;

        if ($count !== null) {
            $parameters['count'] = $count;
        }

        $template = $this->selector->select(
            $this->catalogs->entry($key),
            $this->pluralRule,
            $count,
            $gender,
            $key
        );

        return $this->interpolator->interpolate(
            $template,
            $parameters,
            $key
        );
    }
}

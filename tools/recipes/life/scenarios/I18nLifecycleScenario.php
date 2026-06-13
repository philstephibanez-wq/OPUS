<?php

declare(strict_types=1);

namespace Opus\Recipe\Life\Scenarios;

use ASAP\Recipe\Life\LifeScenarioRunner;
use ASAP\Recipe\Life\RobotActor;
use ASAP\Recipe\Life\RobotScenario;
use ASAP\Recipe\Life\RobotSession;
use ASAP\Recipe\Life\RobotStep;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC LIFE RECIPE: robots use FR/EN/ES locales and pluralized messages. */
final class I18nLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_i18n'; }
    public function scenarioName(): string { return 'I18N'; }
    public function actor(): RobotActor { return new RobotActor('i18n_robot', 'system', 'fr'); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('simulate_locale_users', function (RecipeContext $context, RobotSession $session): void {
            $catalogs = [
                'fr' => [new \ASAP\I18n\Plural\FrenchPluralRule(), 'Bonjour Ada', '2 éléments'],
                'en' => [new \ASAP\I18n\Plural\EnglishPluralRule(), 'Hello Ada', '2 items'],
                'es' => [new \ASAP\I18n\Plural\SpanishPluralRule(), 'Hola Ada', '2 elementos'],
            ];
            foreach ($catalogs as $locale => [$rule, $hello, $plural]) {
                $translator = new \ASAP\I18n\Translator(new \ASAP\I18n\TranslationCatalog(new \ASAP\I18n\LocaleCode($locale), ['hello' => explode(' ', $hello)[0] . ' {name}'], ['items' => ['one' => '{count} item', 'other' => $locale === 'fr' ? '{count} éléments' : ($locale === 'es' ? '{count} elementos' : '{count} items')]]), $rule);
                $context->assert($translator->translate('hello', ['name' => 'Ada']) === $hello, 'OPUS_LIFE_I18N_TRANSLATION_FAILED', $locale);
                $context->assert($translator->plural('items', 2) === $plural, 'OPUS_LIFE_I18N_PLURAL_FAILED', $locale);
            }
        })];
    }
}

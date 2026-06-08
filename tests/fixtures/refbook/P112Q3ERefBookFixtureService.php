<?php

declare(strict_types=1);

namespace ASAP\Tests\Fixtures\RefBook;

use ASAP\RefBook\Attribute\AsapRefBookClass;
use ASAP\RefBook\Attribute\AsapRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

#[AsapRefBookClass(
    domain: 'RefBookFixture',
    role: 'Provide a deterministic fixture for RefBook Reflection tests',
    responsibility: 'Expose typed public methods decorated with functional metadata',
    contracts: ['Reflection owns signatures', 'Attributes own functional descriptions'],
    examples: ['p112q3e-fixture'],
    diagrams: ['p112q3e-refbook-flow'],
    introducedIn: 'P112Q3E'
)]
final class P112Q3ERefBookFixtureService implements RefBookInspectableInterface
{
    /**
     * PUBLIC RefBook fixture domain provider.
     */
    public static function refBookDomain(): string
    {
        return 'RefBookFixture';
    }

    #[AsapRefBookMethod(
        role: 'Build a display label from an identifier and locale',
        behavior: 'Combines the identifier and locale into a deterministic label for scanner assertions.',
        preconditions: ['Identifier is non-empty', 'Locale is a supported fixture language'],
        postconditions: ['The returned label contains both identifier and locale'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookReflectionContractTest.php'],
        examples: ['p112q3e-fixture-label'],
        diagrams: ['p112q3e-refbook-flow'],
        introducedIn: 'P112Q3E'
    )]
    public function buildLabel(string $identifier, string $locale = 'fr'): string
    {
        return $identifier . ':' . $locale;
    }
}

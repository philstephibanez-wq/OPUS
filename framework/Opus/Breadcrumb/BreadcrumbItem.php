<?php

declare(strict_types=1);

namespace Opus\Breadcrumb;

use Opus\Contract\ContractException;
use Opus\RefBook\Attribute\OpusRefBookClass;
use Opus\RefBook\Attribute\OpusRefBookMethod;
use Opus\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: NAVIGATION
 *   role: Value object for one navigable breadcrumb item.
 *   contract:
 *     - carries prepared navigation data only
 *     - must not render HTML
 *     - must not invent route labels or links
 *   examples:
 *     - router-breadcrumb
 *   diagrams:
 *     - router-breadcrumb-runtime
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'NAVIGATION',
    role: 'Carry one breadcrumb navigation item',
    responsibility: 'Represent one prepared breadcrumb label/link pair without rendering HTML or deciding routes.',
    contracts: [
        'Breadcrumb labels must be explicit non-empty strings.',
        'Non-current breadcrumb items must provide an explicit link.',
        'BreadcrumbItem must not render HTML or infer missing route data.',
    ],
    examples: ['router-breadcrumb'],
    diagrams: ['router-breadcrumb-runtime'],
    introducedIn: 'P116C5S'
)]
final class BreadcrumbItem implements RefBookInspectableInterface
{
    #[OpusRefBookMethod(
        role: 'Create one explicit breadcrumb item',
        behavior: 'Stores one label/link/current tuple after validating that it is render-ready.',
        preconditions: ['The label and link were prepared from an explicit route-aware source.'],
        postconditions: ['The item can be exported to a ViewModel array.'],
        sideEffects: ['none'],
        errors: ['OPUS_BREADCRUMB_LABEL_EMPTY', 'OPUS_BREADCRUMB_HREF_EMPTY'],
        testRefs: ['tests/Contract/BreadcrumbContractTest.php'],
        examples: ['router-breadcrumb'],
        diagrams: ['router-breadcrumb-runtime'],
        introducedIn: 'P116C5S'
    )]
    public function __construct(
        public readonly string $label,
        public readonly string $href,
        public readonly bool $current = false
    ) {
        if (trim($this->label) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_LABEL_EMPTY');
        }

        if (!$this->current && trim($this->href) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_HREF_EMPTY', $this->label);
        }
    }

    public static function refBookDomain(): string
    {
        return 'NAVIGATION';
    }

    /** @return array{label:string,href:string,current:bool} */
    #[OpusRefBookMethod(
        role: 'Export one breadcrumb item to a ViewModel row',
        behavior: 'Returns label, href and current fields for templates that render navigation links.',
        preconditions: ['The item passed constructor validation.'],
        postconditions: ['The exported row contains no HTML.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/BreadcrumbContractTest.php'],
        examples: ['router-breadcrumb'],
        diagrams: ['router-breadcrumb-runtime'],
        introducedIn: 'P116C5S'
    )]
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'href' => $this->href,
            'current' => $this->current,
        ];
    }
}

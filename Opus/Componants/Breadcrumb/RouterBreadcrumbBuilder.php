<?php

declare(strict_types=1);

namespace Opus\Breadcrumb;

use Opus\Contract\ContractException;
use Opus\RefBook\Attribute\OpusRefBookClass;
use Opus\RefBook\Attribute\OpusRefBookMethod;
use Opus\RefBook\Contract\RefBookInspectableInterface;
use Opus\Routing\RouteMatch;

/*
 * OPUS_REFBOOK:
 *   domain: NAVIGATION
 *   role: Build breadcrumb ViewModel rows from an explicit Router match.
 *   contract:
 *     - consumes RouteMatch produced by the official Router
 *     - requires explicit labels and hrefs
 *     - must not render HTML or invent fallback labels
 *   examples:
 *     - router-breadcrumb
 *   diagrams:
 *     - router-breadcrumb-runtime
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'NAVIGATION',
    role: 'Build navigable breadcrumb data from an official route match',
    responsibility: 'Transform one RouteMatch and explicit caller-provided labels into breadcrumb ViewModel rows without HTML rendering.',
    contracts: [
        'Breadcrumb construction must start from a RouteMatch produced by the official Router.',
        'The current route name must be explicit and must not be guessed from the template.',
        'Missing home labels, current labels or links fail explicitly. No fallback breadcrumb text is allowed.',
    ],
    examples: ['router-breadcrumb'],
    diagrams: ['router-breadcrumb-runtime'],
    introducedIn: 'P116C5S'
)]
final class RouterBreadcrumbBuilder implements RefBookInspectableInterface
{
    public function __construct(
        private readonly string $homeLabel,
        private readonly string $homeHref
    ) {
        if (trim($this->homeLabel) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_HOME_LABEL_EMPTY');
        }

        if (trim($this->homeHref) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_HOME_HREF_EMPTY');
        }
    }

    public static function refBookDomain(): string
    {
        return 'NAVIGATION';
    }

    /**
     * @return list<array{label:string,href:string,current:bool}>
     */
    #[OpusRefBookMethod(
        role: 'Build a breadcrumb trail for a matched route',
        behavior: 'Creates a home link and a current route item from explicit route-aware data.',
        preconditions: ['The RouteMatch was produced by the official Router.', 'The caller provides the current page label and href.'],
        postconditions: ['Returned rows are render-ready and contain no HTML.'],
        sideEffects: ['none'],
        errors: ['OPUS_BREADCRUMB_CURRENT_LABEL_EMPTY', 'OPUS_BREADCRUMB_CURRENT_HREF_EMPTY'],
        testRefs: ['tests/Contract/BreadcrumbContractTest.php'],
        examples: ['router-breadcrumb'],
        diagrams: ['router-breadcrumb-runtime'],
        introducedIn: 'P116C5S'
    )]
    public function forMatch(RouteMatch $match, string $currentLabel, string $currentHref): array
    {
        if (trim($match->name) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_ROUTE_NAME_EMPTY');
        }

        if (trim($currentLabel) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_CURRENT_LABEL_EMPTY', $match->name);
        }

        if (trim($currentHref) === '') {
            throw ContractException::because('OPUS_BREADCRUMB_CURRENT_HREF_EMPTY', $match->name);
        }

        return [
            (new BreadcrumbItem($this->homeLabel, $this->homeHref, false))->toArray(),
            (new BreadcrumbItem($currentLabel, $currentHref, true))->toArray(),
        ];
    }
}

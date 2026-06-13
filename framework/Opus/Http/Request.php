<?php

declare(strict_types=1);

namespace Opus\Http;

use ASAP\Contract\ContractException;
use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/*
 * OPUS_REFBOOK:
 *   domain: HTTP
 *   role: Class Request belongs to the HTTP Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the HTTP domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - http-overview
 *   diagrams:
 *     - http-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry normalized HTTP request data.
 *
 * Responsibility:
 *   Expose the URI path and HTTP method to Opus engines.
 *
 * Contract:
 *   Request normalization only. No routing, no authorization, no rendering.
 *
 * Since:
 *   P112D1
 */
#[OpusRefBookClass(
    domain: 'HTTP',
    role: 'Carry normalized HTTP request data',
    responsibility: 'Represent the request path and HTTP method passed to routing and secure dispatch boundaries.',
    contracts: [
        'The request path must be explicit and begin with /.',
        'The HTTP method must be explicit and non-empty after trimming.',
        'The request object must not route, authorize, render or read unrelated runtime state.',
    ],
    examples: ['http-overview', 'routing-overview'],
    diagrams: ['http-runtime', 'routing-runtime'],
    introducedIn: 'P112Q3E4'
)]
final class Request implements RefBookInspectableInterface
{
    /**
     * @param string $path Normalized URI path beginning with "/".
     * @param string $method HTTP method.
     */
    #[OpusRefBookMethod(
        role: 'Create one normalized HTTP request object',
        behavior: 'Stores the explicit request path and HTTP method after rejecting invalid request boundaries.',
        preconditions: ['The path starts with /.', 'The method is non-empty after trimming.'],
        postconditions: ['The request path and method are available as readonly values.'],
        sideEffects: ['none'],
        errors: ['OPUS_REQUEST_PATH_INVALID', 'OPUS_REQUEST_METHOD_EMPTY'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['http-overview'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET'
    ) {
        if ($this->path === '' || $this->path[0] !== '/') {
            throw ContractException::because('OPUS_REQUEST_PATH_INVALID', $this->path);
        }

        if (trim($this->method) === '') {
            throw ContractException::because('OPUS_REQUEST_METHOD_EMPTY');
        }
    }

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for HTTP requests',
        behavior: 'Returns the stable HTTP domain used by RefBook scanners and snapshot consumers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is HTTP.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['http-refbook-domain'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public static function refBookDomain(): string
    {
        return 'HTTP';
    }

    /**
     * PUBLIC FACTORY
     *
     * @return self Request built from PHP globals.
     */
    #[OpusRefBookMethod(
        role: 'Build an HTTP request from PHP server globals',
        behavior: 'Reads REQUEST_URI and REQUEST_METHOD, extracts a valid path and creates a normalized Request object.',
        preconditions: ['REQUEST_URI is absent or parseable into a path.', 'REQUEST_METHOD is absent or non-empty after uppercasing.'],
        postconditions: ['Returns a normalized Request object built from the current PHP server boundary.'],
        sideEffects: ['Reads $_SERVER values only.'],
        errors: ['OPUS_REQUEST_URI_INVALID', 'OPUS_REQUEST_PATH_INVALID', 'OPUS_REQUEST_METHOD_EMPTY'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['http-overview'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url((string) $uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            throw ContractException::because('OPUS_REQUEST_URI_INVALID', (string) $uri);
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return new self($path, $method);
    }
}

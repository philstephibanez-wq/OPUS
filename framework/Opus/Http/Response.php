<?php

declare(strict_types=1);

namespace Opus\Http;

use ASAP\Contract\ContractException;
use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;

/*
 * OPUS_REFBOOK:
 *   domain: HTTP
 *   role: Class Response belongs to the HTTP Opus framework domain.
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
 *   Carry HTTP response data.
 *
 * Responsibility:
 *   Emit status, headers and body from a validated controller result.
 *
 * Contract:
 *   Response emits representation only. It does not route, authorize or mutate state.
 *
 * Since:
 *   P112D1
 *
 * Legacy compatibility:
 *   P112O restores static html()/json() constructors.
 */
#[OpusRefBookClass(
    domain: 'HTTP',
    role: 'Carry HTTP response data',
    responsibility: 'Represent the HTTP status, headers and body returned by controller/runtime boundaries.',
    contracts: [
        'The HTTP status must stay inside the standard 100..599 range.',
        'Response objects emit representation data only and must not route, authorize or mutate application state.',
        'HTML and JSON factories must produce explicit Content-Type headers.',
    ],
    examples: ['http-overview', 'response-overview'],
    diagrams: ['http-runtime'],
    introducedIn: 'P112Q3E4'
)]
final class Response
{
    /**
     * @param string $body Response body.
     * @param int $status HTTP status code.
     * @param array<string,string> $headers HTTP headers.
     */
    #[OpusRefBookMethod(
        role: 'Create one HTTP response object',
        behavior: 'Stores the response body, status and headers after rejecting invalid HTTP status codes.',
        preconditions: ['The status code is between 100 and 599.'],
        postconditions: ['The response body, status and headers are available as readonly values.'],
        sideEffects: ['none'],
        errors: ['OPUS_RESPONSE_STATUS_INVALID'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['response-overview'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8']
    ) {
        if ($this->status < 100 || $this->status > 599) {
            throw ContractException::because('OPUS_RESPONSE_STATUS_INVALID', (string) $this->status);
        }
    }

    #[OpusRefBookMethod(
        role: 'Create an HTML response',
        behavior: 'Builds a Response carrying an HTML body and the standard UTF-8 HTML content type.',
        preconditions: ['The status code is between 100 and 599.'],
        postconditions: ['Returns a Response with text/html UTF-8 Content-Type.'],
        sideEffects: ['none'],
        errors: ['OPUS_RESPONSE_STATUS_INVALID'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['response-html'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param mixed $data JSON-serializable data. */
    #[OpusRefBookMethod(
        role: 'Create a JSON response',
        behavior: 'Serializes JSON-compatible data and returns a Response with the standard UTF-8 JSON content type.',
        preconditions: ['The data is JSON serializable.', 'The status code is between 100 and 599.'],
        postconditions: ['Returns a Response with application/json UTF-8 Content-Type.'],
        sideEffects: ['none'],
        errors: ['JSON_THROW_ON_ERROR', 'OPUS_RESPONSE_STATUS_INVALID'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['response-json'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    #[OpusRefBookMethod(
        role: 'Emit the HTTP response',
        behavior: 'Applies the response status, emits each HTTP header and prints the response body.',
        preconditions: ['No previous output has made header emission invalid for the PHP runtime.'],
        postconditions: ['The status code, headers and body have been sent to the PHP output boundary.'],
        sideEffects: ['Calls http_response_code().', 'Calls header() for each response header.', 'Echoes the response body.'],
        errors: ['PHP_HEADER_EMISSION_RUNTIME_ERROR'],
        testRefs: ['tests/Contract/RefBookHttpMetadataContractTest.php'],
        examples: ['response-send'],
        diagrams: ['http-runtime'],
        introducedIn: 'P112Q3E4'
    )]
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}

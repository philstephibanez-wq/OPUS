<?php

declare(strict_types=1);

/**
 * P112Q3E4 HTTP RefBook metadata contract test.
 *
 * Public CLI contract test.
 * Role:
 *   Prove that the Opus HTTP domain is fully covered by the Reflection +
 *   Attributes RefBook contract and still fails request boundaries explicitly.
 */
$root = dirname(__DIR__, 2);
requireRefBookCore($root);
requireHttpRuntime($root);

$httpRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Http';
$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($httpRoot, 'Opus\\Http');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$summary = $validation['summary'];

assertSame(0, $summary['load_errors'], 'HTTP scan must not have load errors.');
assertSame(0, $summary['class_metadata_missing'], 'Every HTTP class must expose OpusRefBookClass metadata.');
assertSame(0, $summary['method_metadata_missing'], 'Every HTTP public method must expose OpusRefBookMethod metadata.');
assertSame(0, $summary['violations'], 'HTTP RefBook contract must have zero violations.');
assertSame(2, $summary['classes'], 'Expected Request and Response HTTP symbols in the fourth critical domain baseline.');
assertSame(7, $summary['public_methods'], 'Expected seven HTTP public methods after Request inspectable provider and Response live-symbol coverage.');

$classes = [];
foreach ($result->classes() as $class) {
    $data = $class->toArray();
    $classes[$data['name']] = $data;
    assertSame('HTTP', $data['metadata']['domain'] ?? null, 'Every HTTP class metadata must declare domain HTTP: ' . $data['name']);
    assertNonEmptyString($data['metadata']['role'] ?? '', 'Class role missing: ' . $data['name']);
    assertNonEmptyString($data['metadata']['responsibility'] ?? '', 'Class responsibility missing: ' . $data['name']);
    assertNotEmptyArray($data['metadata']['contracts'] ?? [], 'Class contracts missing: ' . $data['name']);
    assertContains('http-runtime', $data['metadata']['diagrams'] ?? [], 'HTTP class must link the generated runtime diagram: ' . $data['name']);

    foreach ($data['methods'] as $method) {
        assertNonEmptyString($method['metadata']['role'] ?? '', 'Method role missing: ' . $data['name'] . '::' . $method['name']);
        assertNonEmptyString($method['metadata']['behavior'] ?? '', 'Method behavior missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['side_effects'] ?? [], 'Method side effects contract missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['errors'] ?? [], 'Method errors contract missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('tests/Contract/RefBookHttpMetadataContractTest.php', $method['metadata']['test_refs'] ?? [], 'Method test reference missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('http-runtime', $method['metadata']['diagrams'] ?? [], 'Method diagram link missing: ' . $data['name'] . '::' . $method['name']);
        assertSame('P112Q3E4', $method['metadata']['introduced_in'] ?? '', 'Method delivery marker missing: ' . $data['name'] . '::' . $method['name']);
    }
}

assertHasClass($classes, 'Opus\\Http\\Request');
assertHasClass($classes, 'Opus\\Http\\Response');
assertSame(true, $classes['Opus\\Http\\Request']['implements_refbook_inspectable'], 'Request must opt in to RefBookInspectableInterface.');

$requestClass = $classes['Opus\\Http\\Request'];
$constructor = findMethod($requestClass['methods'], '__construct');
assertSame('string', $constructor['parameters'][0]['type'] ?? null, 'Request::__construct path parameter type must come from Reflection.');
assertSame('string', $constructor['parameters'][1]['type'] ?? null, 'Request::__construct method parameter type must come from Reflection.');
assertContains('OPUS_REQUEST_PATH_INVALID', $constructor['metadata']['errors'] ?? [], 'Request::__construct must declare invalid path error.');

$refBookDomain = findMethod($requestClass['methods'], 'refBookDomain');
assertSame('string', $refBookDomain['return_type'], 'Request::refBookDomain return type must come from Reflection.');

$fromGlobals = findMethod($requestClass['methods'], 'fromGlobals');
assertSame('Opus\\Http\\Request', $fromGlobals['return_type'], 'Request::fromGlobals return type must come from Reflection scanner normalization.');
assertContains('OPUS_REQUEST_URI_INVALID', $fromGlobals['metadata']['errors'] ?? [], 'Request::fromGlobals must declare invalid URI error.');

$responseClass = $classes['Opus\\Http\\Response'];
$responseConstructor = findMethod($responseClass['methods'], '__construct');
assertSame('string', $responseConstructor['parameters'][0]['type'] ?? null, 'Response::__construct body parameter type must come from Reflection.');
assertSame('int', $responseConstructor['parameters'][1]['type'] ?? null, 'Response::__construct status parameter type must come from Reflection.');
assertContains('OPUS_RESPONSE_STATUS_INVALID', $responseConstructor['metadata']['errors'] ?? [], 'Response::__construct must declare invalid status error.');

$htmlFactory = findMethod($responseClass['methods'], 'html');
assertSame('Opus\\Http\\Response', $htmlFactory['return_type'], 'Response::html return type must come from Reflection scanner normalization.');
assertContains('OPUS_RESPONSE_STATUS_INVALID', $htmlFactory['metadata']['errors'] ?? [], 'Response::html must declare invalid status error.');

$jsonFactory = findMethod($responseClass['methods'], 'json');
assertSame('Opus\\Http\\Response', $jsonFactory['return_type'], 'Response::json return type must come from Reflection scanner normalization.');
assertContains('JSON_THROW_ON_ERROR', $jsonFactory['metadata']['errors'] ?? [], 'Response::json must declare JSON serialization error.');

$sendMethod = findMethod($responseClass['methods'], 'send');
assertSame('void', $sendMethod['return_type'], 'Response::send return type must come from Reflection.');
assertContains('Calls header() for each response header.', $sendMethod['metadata']['side_effects'] ?? [], 'Response::send must declare header side effect.');

$request = new ASAP\Http\Request('/demo', 'post');
assertSame('/demo', $request->path, 'HTTP runtime sanity: path mismatch.');
assertSame('post', $request->method, 'HTTP runtime sanity: method mismatch.');

try {
    new ASAP\Http\Request('demo', 'GET');
    fail('HTTP runtime sanity: invalid path must fail explicitly.');
} catch (Opus\Contract\ContractException $exception) {
    assertContains('OPUS_REQUEST_PATH_INVALID', $exception->getMessage(), 'HTTP runtime sanity: invalid path code mismatch.');
}

try {
    new ASAP\Http\Request('/demo', '');
    fail('HTTP runtime sanity: empty method must fail explicitly.');
} catch (Opus\Contract\ContractException $exception) {
    assertContains('OPUS_REQUEST_METHOD_EMPTY', $exception->getMessage(), 'HTTP runtime sanity: empty method code mismatch.');
}

$response = new ASAP\Http\Response('ok', 201, ['Content-Type' => 'text/plain']);
assertSame('ok', $response->body, 'HTTP runtime sanity: response body mismatch.');
assertSame(201, $response->status, 'HTTP runtime sanity: response status mismatch.');
assertSame('text/plain', $response->headers['Content-Type'] ?? '', 'HTTP runtime sanity: response header mismatch.');

$htmlResponse = ASAP\Http\Response::html('<p>ok</p>');
assertSame('text/html; charset=utf-8', $htmlResponse->headers['Content-Type'] ?? '', 'HTTP runtime sanity: HTML content-type mismatch.');

$jsonResponse = ASAP\Http\Response::json(['ok' => true]);
assertSame('{"ok":true}', $jsonResponse->body, 'HTTP runtime sanity: JSON body mismatch.');
assertSame('application/json; charset=utf-8', $jsonResponse->headers['Content-Type'] ?? '', 'HTTP runtime sanity: JSON content-type mismatch.');

try {
    new ASAP\Http\Response('bad', 99);
    fail('HTTP runtime sanity: invalid response status must fail explicitly.');
} catch (Opus\Contract\ContractException $exception) {
    assertContains('OPUS_RESPONSE_STATUS_INVALID', $exception->getMessage(), 'HTTP runtime sanity: invalid status code mismatch.');
}

$oldServer = $_SERVER;
try {
    $_SERVER['REQUEST_URI'] = '/alpha/beta?x=1';
    $_SERVER['REQUEST_METHOD'] = 'post';
    $fromGlobalsRequest = ASAP\Http\Request::fromGlobals();
    assertSame('/alpha/beta', $fromGlobalsRequest->path, 'HTTP runtime sanity: fromGlobals path mismatch.');
    assertSame('POST', $fromGlobalsRequest->method, 'HTTP runtime sanity: fromGlobals method mismatch.');
} finally {
    $_SERVER = $oldServer;
}

echo 'P112Q3E4_REFBOOK_HTTP_METADATA_CONTRACT_UNIT_OK' . PHP_EOL;
exit(0);

function requireRefBookCore(string $root): void
{
    $files = [
        'framework/Opus/RefBook/Attribute/OpusRefBookClass.php',
        'framework/Opus/RefBook/Attribute/OpusRefBookMethod.php',
        'framework/Opus/RefBook/Contract/RefBookInspectableInterface.php',
        'framework/Opus/RefBook/Model/RefBookMethodEntry.php',
        'framework/Opus/RefBook/Model/RefBookClassEntry.php',
        'framework/Opus/RefBook/Model/RefBookScanResult.php',
        'framework/Opus/RefBook/RefBookReflectionScanner.php',
        'framework/Opus/RefBook/RefBookContractValidator.php',
        'framework/Opus/RefBook/RefBookSnapshotBuilder.php',
    ];
    foreach ($files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            fwrite(STDERR, 'P112Q3E4_UNIT_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
            exit(1);
        }
        require_once $path;
    }
}

function requireHttpRuntime(string $root): void
{
    $files = [
        'framework/Opus/Contract/ContractException.php',
        'framework/Opus/Http/Request.php',
        'framework/Opus/Http/Response.php',
    ];
    foreach ($files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            fwrite(STDERR, 'P112Q3E4_UNIT_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
            exit(1);
        }
        require_once $path;
    }
}

/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'P112Q3E4_UNIT_FAILED: ' . $message . PHP_EOL);
        fwrite(STDERR, 'Expected=' . var_export($expected, true) . ' Actual=' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertNonEmptyString(mixed $value, string $message): void
{
    if (!is_string($value) || trim($value) === '') {
        fail($message);
    }
}

function assertNotEmptyArray(mixed $value, string $message): void
{
    if (!is_array($value) || $value === []) {
        fail($message);
    }
}

/** @param array<int|string,mixed> $haystack */
function assertContains(string $needle, array|string $haystack, string $message): void
{
    if (is_array($haystack)) {
        if (!in_array($needle, $haystack, true)) {
            fail($message);
        }
        return;
    }
    if (!str_contains($haystack, $needle)) {
        fail($message);
    }
}

/** @param array<string,array<string,mixed>> $classes */
function assertHasClass(array $classes, string $name): void
{
    if (!isset($classes[$name])) {
        fail('Expected HTTP class missing from scan: ' . $name);
    }
}

/** @param array<int,array<string,mixed>> $methods */
function findMethod(array $methods, string $name): array
{
    foreach ($methods as $method) {
        if ($method['name'] === $name) {
            return $method;
        }
    }
    fail('Method missing from scan: ' . $name);
}

function fail(string $message): void
{
    fwrite(STDERR, 'P112Q3E4_UNIT_FAILED: ' . $message . PHP_EOL);
    exit(1);
}

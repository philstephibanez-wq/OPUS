<?php
declare(strict_types=1);

/**
 * PUBLIC SMOKE TEST
 *
 * Role:
 *   Validate the P113B8 RefBook search delivery without requiring Apache,
 *   UwAmp, .htaccess, database or ASAP_ROOT.
 *
 * Contract:
 *   Static/application smoke only. It checks that the dedicated search service,
 *   template, layout integration, CSS hooks and FR/EN/ES labels are present and
 *   that a real manifest query returns navigable results.
 *
 * Version:
 *   P113B8_REFBOOK_SEARCH
 */

$root = dirname(__DIR__, 2);

require_once $root . '/application/reference/Service/ManifestRepository.php';
require_once $root . '/application/reference/Service/ReferenceContentService.php';
require_once $root . '/application/reference/Service/ReferenceCatalogService.php';
require_once $root . '/application/reference/Service/ReferenceSearchService.php';

use ASAPRefBook\Reference\Service\ManifestRepository;
use ASAPRefBook\Reference\Service\ReferenceCatalogService;
use ASAPRefBook\Reference\Service\ReferenceContentService;
use ASAPRefBook\Reference\Service\ReferenceSearchService;

function fail(string $message): never
{
    fwrite(STDERR, 'P113B8_SEARCH_SMOKE_FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function assert_file_contains(string $file, string $needle): void
{
    if (!is_file($file)) {
        fail('missing file ' . $file);
    }

    $body = (string) file_get_contents($file);
    if (!str_contains($body, $needle)) {
        fail('missing needle ' . $needle . ' in ' . $file);
    }
}

$layout = $root . '/application/reference/templates/layout.twig';
$searchTemplate = $root . '/application/reference/templates/pages/search.twig';
$css = $root . '/public/assets/css/refbook.css';
$controller = $root . '/application/reference/Controller/HomeController.php';
$abstractController = $root . '/application/reference/Controller/AbstractRefBookController.php';

assert_file_contains($layout, 'class="top-search"');
assert_file_contains($layout, 'page" value="search"');
assert_file_contains($layout, 'refbook.css?v=P113B8');
assert_file_contains($searchTemplate, 'class="search-result-card"');
assert_file_contains($css, 'P113B8_REFBOOK_SEARCH');
assert_file_contains($css, '.top-search');
assert_file_contains($css, '.search-result-card');
assert_file_contains($controller, "if (\$page === 'search')");
assert_file_contains($abstractController, 'ReferenceSearchService');

foreach (['fr', 'en', 'es'] as $language) {
    $file = $root . '/content/refbook/i18n/' . $language . '.json';
    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        fail('invalid i18n json ' . $language);
    }

    if (($json['labels']['sidebar']['search'] ?? null) === null) {
        fail('missing sidebar search label ' . $language);
    }

    foreach (['title', 'placeholder', 'results_title', 'empty_title', 'type_symbol'] as $key) {
        if (!isset($json['labels']['search'][$key])) {
            fail('missing search label ' . $language . ':' . $key);
        }
    }
}

$content = new ReferenceContentService($root . '/content/refbook/i18n', 'fr');
$catalog = new ReferenceCatalogService(
    new ManifestRepository($root . '/var/data/api_reference.generated.json'),
    $content
);
$search = new ReferenceSearchService($catalog, $content);
$routerResults = $search->search('Router');
$aclResults = $search->search('ACL');
$emptyResults = $search->search('');

if ($routerResults['count'] < 1 || $aclResults['count'] < 1) {
    fail('expected Router and ACL queries to return results');
}

if ($emptyResults['has_query'] !== false || $emptyResults['count'] !== 0) {
    fail('empty query must stay an empty UI state');
}

foreach ($routerResults['results'] as $result) {
    foreach (['type', 'page', 'title', 'subtitle', 'meta', 'snippet', 'badges', 'score'] as $key) {
        if (!array_key_exists($key, $result)) {
            fail('missing result key ' . $key);
        }
    }
}

echo 'P113B8_REFBOOK_SEARCH_SMOKE_OK' . PHP_EOL;

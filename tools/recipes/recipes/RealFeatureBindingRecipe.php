<?php

declare(strict_types=1);

namespace ASAP\Recipe\Recipes;

use ASAP\Recipe\RecipeAssertionFailedException;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/**
 * PUBLIC RECIPE
 *
 * Role:
 *   Bind the global ASAP recipe suite to the real historical ASAP_REF_BOOK
 *   application instead of validating only sandbox witness pages.
 *
 * Responsibility:
 *   Verify the real reference book root, real UwAmp HTTP endpoints, legacy
 *   browser recipe URLs and the historical Mailpit mail recipe.
 *
 * Contract:
 *   This recipe intentionally fails if ASAP_REF_BOOK, UwAmp HTTP, Mailpit, or
 *   legacy URLs are unavailable. A sandbox-only success is not accepted as a
 *   global anti-regression proof.
 */
final class RealFeatureBindingRecipe implements RecipeInterface
{
    public function name(): string
    {
        return 'real_feature_binding';
    }

    /** @return string[] */
    public function run(RecipeContext $context): array
    {
        $refBookRoot = $this->refBookRoot($context);
        $baseUrl = $this->refBookBaseUrl();

        $this->assertRefBookFiles($context, $refBookRoot);
        $this->assertLegacyHttpPages($context, $baseUrl);
        $this->assertHistoricalMailpitRecipe($context, $baseUrl);

        $reportPath = $this->writeBindingReport($context, $refBookRoot, $baseUrl);
        $context->diagnostic('ASAP_REAL_FEATURE_BINDING_REPORT=' . $reportPath);

        return [
            'ASAP_REAL_REFBOOK_ROOT_OK',
            'ASAP_REAL_REFBOOK_HTTP_OK',
            'ASAP_REAL_REFBOOK_LEGACY_PAGES_OK',
            'ASAP_REAL_REFBOOK_MAIL_RECIPE_OK',
            'ASAP_REAL_FEATURE_BINDING_OK',
        ];
    }

    private function refBookRoot(RecipeContext $context): string
    {
        $configured = trim((string)(getenv('ASAP_RECIPE_REFBOOK_ROOT') ?: ''));
        if ($configured !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), DIRECTORY_SEPARATOR);
        }

        return dirname($context->rootPath()) . DIRECTORY_SEPARATOR . 'ASAP_REF_BOOK';
    }

    private function refBookBaseUrl(): string
    {
        return rtrim((string)(getenv('ASAP_RECIPE_REFBOOK_BASE_URL') ?: 'http://127.0.0.1/ASAP_REF_BOOK'), '/');
    }

    private function mailpitHttpBase(): string
    {
        return rtrim((string)(getenv('ASAP_RECIPE_MAILPIT_HTTP') ?: 'http://127.0.0.1:8025'), '/');
    }

    private function assertRefBookFiles(RecipeContext $context, string $refBookRoot): void
    {
        $context->assert(is_dir($refBookRoot), 'ASAP_REAL_REFBOOK_ROOT_MISSING', $refBookRoot);

        foreach ([
            'public/index.php',
            'sites/asap-reference/site.xml',
            'application/reference/templates/layout.twig',
        ] as $relative) {
            $path = $refBookRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $context->assert(is_file($path), 'ASAP_REAL_REFBOOK_REQUIRED_FILE_MISSING', $relative . ' :: ' . $path);
        }
    }

    private function assertLegacyHttpPages(RecipeContext $context, string $baseUrl): void
    {
        foreach ($this->legacyHttpPages() as $label => $path) {
            $response = $this->http($baseUrl . $path);
            $context->assert($response['status'] === 200, 'ASAP_REAL_REFBOOK_HTTP_PAGE_FAILED', $label . ' :: ' . $baseUrl . $path . ' :: ' . (string)$response['status']);
            $context->assert($response['body'] !== '', 'ASAP_REAL_REFBOOK_HTTP_PAGE_EMPTY', $label . ' :: ' . $baseUrl . $path);
        }
    }

    /** @return array<string,string> */
    private function legacyHttpPages(): array
    {
        return [
            'home' => '/',
            'auto_recipe' => '/auto-recipe',
            'panther_browser_testing' => '/panther-browser-testing',
            'total_apache_recipe' => '/total-apache-recipe',
            'ui_functional_target' => '/asap-ui-functional-target.html',
        ];
    }

    private function assertHistoricalMailpitRecipe(RecipeContext $context, string $baseUrl): void
    {
        $before = $this->mailpitCount();
        foreach (['one', 'two', 'three'] as $scenario) {
            $url = $baseUrl . '/asap-mail-recipe.php?scenario=' . rawurlencode($scenario) . '&transport=mailpit_smtp';
            $response = $this->http($url, 12.0);
            $context->assert($response['status'] === 200, 'ASAP_REAL_REFBOOK_MAIL_RECIPE_HTTP_FAILED', $scenario . ' :: ' . (string)$response['status'] . ' :: ' . $response['body']);
            $context->assert(str_contains($response['body'], 'MAIL') || str_contains($response['body'], 'mail') || str_contains($response['body'], 'OK'), 'ASAP_REAL_REFBOOK_MAIL_RECIPE_MARKER_MISSING', $scenario);
        }

        $deadline = microtime(true) + 12.0;
        do {
            $after = $this->mailpitCount();
            if ($after >= $before + 3) {
                return;
            }
            usleep(300000);
        } while (microtime(true) < $deadline);

        throw RecipeAssertionFailedException::because('ASAP_REAL_REFBOOK_MAILPIT_COUNT_NOT_INCREASED', 'before=' . (string)$before . ' after=' . (string)($after ?? -1));
    }

    /** @return array{status:int,body:string} */
    private function http(string $url, float $timeoutSeconds = 5.0): array
    {
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => $timeoutSeconds]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                $status = (int)$matches[1];
                break;
            }
        }

        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }

    private function mailpitCount(): int
    {
        $json = $this->httpJson($this->mailpitHttpBase() . '/api/v1/messages?limit=1');
        foreach (['total', 'Total', 'count', 'Count'] as $key) {
            if (isset($json[$key]) && is_numeric($json[$key])) {
                return (int)$json[$key];
            }
        }
        if (isset($json['messages']) && is_array($json['messages'])) {
            return count($json['messages']);
        }
        if (isset($json['Messages']) && is_array($json['Messages'])) {
            return count($json['Messages']);
        }

        throw RecipeAssertionFailedException::because('ASAP_REAL_REFBOOK_MAILPIT_COUNT_UNREADABLE', json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** @return array<string,mixed> */
    private function httpJson(string $url): array
    {
        $response = $this->http($url, 5.0);
        if ($response['status'] !== 200) {
            throw RecipeAssertionFailedException::because('ASAP_REAL_REFBOOK_MAILPIT_API_FAILED', $url . ' :: ' . (string)$response['status']);
        }
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw RecipeAssertionFailedException::because('ASAP_REAL_REFBOOK_MAILPIT_API_JSON_INVALID', $url);
        }

        return $decoded;
    }

    private function writeBindingReport(RecipeContext $context, string $refBookRoot, string $baseUrl): string
    {
        $dir = $context->runtimePath() . DIRECTORY_SEPARATOR . 'real_feature_binding';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw RecipeAssertionFailedException::because('ASAP_REAL_FEATURE_BINDING_REPORT_DIR_FAILED', $dir);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'binding.json';
        file_put_contents($path, json_encode([
            'run_id' => $context->runId(),
            'refbook_root' => $refBookRoot,
            'base_url' => $baseUrl,
            'legacy_pages' => $this->legacyHttpPages(),
            'mailpit_http' => $this->mailpitHttpBase(),
            'status' => 'OK',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}

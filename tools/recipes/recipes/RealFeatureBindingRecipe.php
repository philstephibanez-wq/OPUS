<?php

declare(strict_types=1);

namespace Opus\Recipe\Recipes;

use ASAP\Recipe\RecipeAssertionFailedException;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/**
 * PUBLIC RECIPE
 *
 * Role:
 *   Bind the global Opus recipe suite to the real historical OPUS_REF_BOOK
 *   application instead of validating only sandbox witness pages.
 *
 * Responsibility:
 *   Verify the real reference book root, real UwAmp HTTP endpoints, legacy
 *   browser recipe URLs and the historical Mailpit mail recipe.
 *
 * Contract:
 *   This recipe intentionally fails if OPUS_REF_BOOK, UwAmp HTTP, Mailpit, or
 *   legacy URLs are unavailable. A sandbox-only success is not accepted as a
 *   global anti-regression proof.
 *
 * Diagnostics:
 *   Each real HTTP check writes a diagnostic JSON artifact and the raw response
 *   body under the recipe runtime directory. No future 500 may fail as an opaque
 *   status-only error.
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
        $diagnosticsDir = $this->diagnosticsDir($context);

        $this->assertRefBookFiles($context, $refBookRoot);
        $this->assertLegacyHttpPages($context, $baseUrl, $diagnosticsDir);
        $this->assertHistoricalMailpitRecipe($context, $baseUrl, $diagnosticsDir);

        $reportPath = $this->writeBindingReport($context, $refBookRoot, $baseUrl, $diagnosticsDir);
        $context->diagnostic('OPUS_REAL_FEATURE_BINDING_REPORT=' . $reportPath);
        $context->diagnostic('OPUS_REAL_FEATURE_BINDING_DIAGNOSTICS_DIR=' . $diagnosticsDir);

        return [
            'OPUS_REAL_REFBOOK_ROOT_OK',
            'OPUS_REAL_REFBOOK_HTTP_OK',
            'OPUS_REAL_REFBOOK_LEGACY_PAGES_OK',
            'OPUS_REAL_REFBOOK_MAIL_RECIPE_OK',
            'OPUS_REAL_FEATURE_BINDING_DIAGNOSTICS_OK',
            'OPUS_REAL_FEATURE_BINDING_OK',
        ];
    }

    private function refBookRoot(RecipeContext $context): string
    {
        $configured = trim((string)(getenv('OPUS_RECIPE_REFBOOK_ROOT') ?: ''));
        if ($configured !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), DIRECTORY_SEPARATOR);
        }

        return dirname($context->rootPath()) . DIRECTORY_SEPARATOR . 'OPUS_REF_BOOK';
    }

    private function refBookBaseUrl(): string
    {
        return rtrim((string)(getenv('OPUS_RECIPE_REFBOOK_BASE_URL') ?: 'http://127.0.0.1/OPUS_REF_BOOK'), '/');
    }

    private function mailpitHttpBase(): string
    {
        return rtrim((string)(getenv('OPUS_RECIPE_MAILPIT_HTTP') ?: 'http://127.0.0.1:8025'), '/');
    }

    private function diagnosticsDir(RecipeContext $context): string
    {
        $dir = $context->runtimePath() . DIRECTORY_SEPARATOR . 'real_feature_binding' . DIRECTORY_SEPARATOR . 'diagnostics';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw RecipeAssertionFailedException::because('OPUS_REAL_FEATURE_BINDING_DIAGNOSTICS_DIR_FAILED', $dir);
        }

        return $dir;
    }

    private function assertRefBookFiles(RecipeContext $context, string $refBookRoot): void
    {
        $context->assert(is_dir($refBookRoot), 'OPUS_REAL_REFBOOK_ROOT_MISSING', $refBookRoot);

        foreach ([
            'public/index.php',
            'sites/opus-reference/site.xml',
            'application/reference/templates/layout.twig',
        ] as $relative) {
            $path = $refBookRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $context->assert(is_file($path), 'OPUS_REAL_REFBOOK_REQUIRED_FILE_MISSING', $relative . ' :: ' . $path);
        }
    }

    private function assertLegacyHttpPages(RecipeContext $context, string $baseUrl, string $diagnosticsDir): void
    {
        foreach ($this->legacyHttpPages() as $label => $path) {
            $url = $baseUrl . $path;
            $response = $this->http($url);
            $diagnostic = $this->writeHttpDiagnostic($diagnosticsDir, 'http_' . $label, $url, $response);
            $context->diagnostic('OPUS_REAL_REFBOOK_HTTP_DIAGNOSTIC=' . $diagnostic);

            $context->assert(
                $response['status'] === 200,
                'OPUS_REAL_REFBOOK_HTTP_PAGE_FAILED',
                $label . ' :: ' . $url . ' :: ' . (string)$response['status'] . ' :: diagnostic=' . $diagnostic . ' :: excerpt=' . $this->bodyExcerpt($response['body'])
            );
            $context->assert(
                $response['body'] !== '',
                'OPUS_REAL_REFBOOK_HTTP_PAGE_EMPTY',
                $label . ' :: ' . $url . ' :: diagnostic=' . $diagnostic
            );
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
            'ui_functional_target' => '/opus-ui-functional-target.html',
        ];
    }

    private function assertHistoricalMailpitRecipe(RecipeContext $context, string $baseUrl, string $diagnosticsDir): void
    {
        $before = $this->mailpitCount();
        foreach (['one', 'two', 'three'] as $scenario) {
            $url = $baseUrl . '/opus-mail-recipe.php?scenario=' . rawurlencode($scenario) . '&transport=mailpit_smtp';
            $response = $this->http($url, 12.0);
            $diagnostic = $this->writeHttpDiagnostic($diagnosticsDir, 'mailpit_recipe_' . $scenario, $url, $response);
            $context->diagnostic('OPUS_REAL_REFBOOK_MAIL_DIAGNOSTIC=' . $diagnostic);

            $context->assert(
                $response['status'] === 200,
                'OPUS_REAL_REFBOOK_MAIL_RECIPE_HTTP_FAILED',
                $scenario . ' :: ' . (string)$response['status'] . ' :: diagnostic=' . $diagnostic . ' :: excerpt=' . $this->bodyExcerpt($response['body'])
            );
            $context->assert(
                str_contains($response['body'], 'MAIL') || str_contains($response['body'], 'mail') || str_contains($response['body'], 'OK'),
                'OPUS_REAL_REFBOOK_MAIL_RECIPE_MARKER_MISSING',
                $scenario . ' :: diagnostic=' . $diagnostic
            );
        }

        $deadline = microtime(true) + 12.0;
        do {
            $after = $this->mailpitCount();
            if ($after >= $before + 3) {
                return;
            }
            usleep(300000);
        } while (microtime(true) < $deadline);

        throw RecipeAssertionFailedException::because('OPUS_REAL_REFBOOK_MAILPIT_COUNT_NOT_INCREASED', 'before=' . (string)$before . ' after=' . (string)($after ?? -1));
    }

    /** @return array{status:int,body:string,headers:array<int,string>} */
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

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'headers' => array_values($headers),
        ];
    }

    /**
     * @param array{status:int,body:string,headers:array<int,string>} $response
     */
    private function writeHttpDiagnostic(string $diagnosticsDir, string $label, string $url, array $response): string
    {
        $safeLabel = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $label) ?: 'http';
        $bodyFile = $diagnosticsDir . DIRECTORY_SEPARATOR . $safeLabel . '.body.txt';
        $jsonFile = $diagnosticsDir . DIRECTORY_SEPARATOR . $safeLabel . '.json';

        file_put_contents($bodyFile, $response['body']);
        file_put_contents($jsonFile, json_encode([
            'schema' => 'OPUS_REAL_HTTP_DIAGNOSTIC_V1',
            'label' => $label,
            'url' => $url,
            'status' => $response['status'],
            'headers' => $response['headers'],
            'body_file' => $bodyFile,
            'body_bytes' => strlen($response['body']),
            'body_excerpt' => $this->bodyExcerpt($response['body']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $jsonFile;
    }

    private function bodyExcerpt(string $body): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', strip_tags($body)));
        if ($text === '') {
            $text = trim((string)preg_replace('/\s+/', ' ', $body));
        }

        return substr($text, 0, 700);
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

        throw RecipeAssertionFailedException::because('OPUS_REAL_REFBOOK_MAILPIT_COUNT_UNREADABLE', json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** @return array<string,mixed> */
    private function httpJson(string $url): array
    {
        $response = $this->http($url, 5.0);
        if ($response['status'] !== 200) {
            throw RecipeAssertionFailedException::because('OPUS_REAL_REFBOOK_MAILPIT_API_FAILED', $url . ' :: ' . (string)$response['status']);
        }
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw RecipeAssertionFailedException::because('OPUS_REAL_REFBOOK_MAILPIT_API_JSON_INVALID', $url);
        }

        return $decoded;
    }

    private function writeBindingReport(RecipeContext $context, string $refBookRoot, string $baseUrl, string $diagnosticsDir): string
    {
        $dir = $context->runtimePath() . DIRECTORY_SEPARATOR . 'real_feature_binding';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw RecipeAssertionFailedException::because('OPUS_REAL_FEATURE_BINDING_REPORT_DIR_FAILED', $dir);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'binding.json';
        file_put_contents($path, json_encode([
            'run_id' => $context->runId(),
            'refbook_root' => $refBookRoot,
            'base_url' => $baseUrl,
            'legacy_pages' => $this->legacyHttpPages(),
            'mailpit_http' => $this->mailpitHttpBase(),
            'diagnostics_dir' => $diagnosticsDir,
            'status' => 'OK',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}

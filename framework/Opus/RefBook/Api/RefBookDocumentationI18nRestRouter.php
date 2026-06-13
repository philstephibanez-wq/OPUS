<?php

declare(strict_types=1);

namespace Opus\RefBook\Api;

use ASAP\RefBook\I18n\RefBookDocumentationLocale;
use Throwable;

/*
 * OPUS_REFBOOK:
 *   domain: REFBOOK
 *   role: Class RefBookDocumentationI18nRestRouter belongs to the REFBOOK Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REFBOOK domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - refbook-overview
 *   diagrams:
 *     - refbook-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC RefBook documentation I18N REST router.
 *
 * Role:
 *   Provide URL-level language selection for localized reflection documentation.
 *
 * URL contract:
 *   GET /api/refbook/{lang}/snapshot
 *   GET /api/refbook/{lang}/symbols
 *   GET /api/refbook/{lang}/symbols/{id}
 *
 * Contract:
 *   - lang is mandatory in the path;
 *   - unsupported lang fails explicitly;
 *   - no query-string language fallback;
 *   - missing translations are explicit API errors.
 */
final class RefBookDocumentationI18nRestRouter
{
    /**
     * @param callable():array<string,mixed> $snapshotProvider
     */
    public function __construct(
        private readonly mixed $snapshotProvider,
        private readonly LocalizedRefBookDocumentationProvider $localizedProvider
    ) {
        if (!is_callable($this->snapshotProvider)) {
            throw new \InvalidArgumentException('OPUS_REFBOOK_DOC_SNAPSHOT_PROVIDER_NOT_CALLABLE');
        }
    }

    /**
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method, string $requestUri): array
    {
        if (strtoupper($method) !== 'GET') {
            return $this->response(405, ['ok' => false, 'error' => 'OPUS_REFBOOK_DOC_METHOD_NOT_ALLOWED']);
        }

        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path)) {
            return $this->response(400, ['ok' => false, 'error' => 'OPUS_REFBOOK_DOC_PATH_INVALID']);
        }

        if (!preg_match('~^/api/refbook/(?P<lang>[a-z]{2})/(?P<resource>snapshot|symbols)(?:/(?P<id>[A-Za-z0-9_.-]+))?$~', $path, $matches)) {
            return $this->response(404, ['ok' => false, 'error' => 'OPUS_REFBOOK_DOC_ROUTE_NOT_FOUND']);
        }

        try {
            $language = RefBookDocumentationLocale::assertSupported($matches['lang']);
            $snapshot = ($this->snapshotProvider)();

            if (!is_array($snapshot)) {
                return $this->response(500, ['ok' => false, 'error' => 'OPUS_REFBOOK_DOC_SNAPSHOT_INVALID']);
            }

            if ($matches['resource'] === 'snapshot') {
                return $this->response(200, [
                    'ok' => true,
                    'language' => $language,
                    'data' => $this->localizedProvider->localizeSnapshot($snapshot, $language),
                ]);
            }

            $symbols = $this->symbols($snapshot);
            if (($matches['id'] ?? '') !== '') {
                $symbol = $this->symbolById($symbols, $matches['id']);
                if ($symbol === null) {
                    return $this->response(404, ['ok' => false, 'error' => 'OPUS_REFBOOK_DOC_SYMBOL_NOT_FOUND', 'id' => $matches['id']]);
                }

                return $this->response(200, [
                    'ok' => true,
                    'language' => $language,
                    'data' => $this->localizedProvider->localizeSymbol($symbol, $language),
                ]);
            }

            return $this->response(200, [
                'ok' => true,
                'language' => $language,
                'data' => $this->localizedProvider->localizeSnapshot(['symbols' => $symbols], $language)['symbols'],
            ]);
        } catch (Throwable $exception) {
            return $this->response(500, [
                'ok' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return list<array<string,mixed>>
     */
    private function symbols(array $snapshot): array
    {
        $symbols = $snapshot['symbols'] ?? $snapshot['classes'] ?? [];
        if (!is_array($symbols)) {
            return [];
        }

        if (array_is_list($symbols)) {
            return $symbols;
        }

        return array_values(array_filter($symbols, static fn (mixed $symbol): bool => is_array($symbol)));
    }

    /**
     * @param list<array<string,mixed>> $symbols
     * @return array<string,mixed>|null
     */
    private function symbolById(array $symbols, string $id): ?array
    {
        foreach ($symbols as $symbol) {
            foreach (['id', 'name', 'class', 'fqcn'] as $key) {
                if (($symbol[$key] ?? null) === $id) {
                    return $symbol;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $body
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    private function response(int $status, array $body): array
    {
        return [
            'status' => $status,
            'headers' => ['content-type' => 'application/json; charset=utf-8'],
            'body' => $body,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Opus\Http;

use Opus\File\StructuredFileLoader;
use Opus\I18n\Locale;
use Opus\Profiler\ProfilerInterface;
use RuntimeException;

/**
 * Generic OPUS localized-route resolver.
 *
 * Canonical application route names remain stable and locale-independent.
 * Only the public frontend path is localized. Route resource tails can be
 * declared opaque (for example source/<real file path>).
 */
final class LocalizedRouteResolver implements LocalizedRouteResolverInterface
{
    private const CONTRACT = 'OPUS_LOCALIZED_ROUTE_CATALOG_V1';

    /** @var array<string,array{tail:bool,paths:array<string,string>}> */
    private array $routes = [];

    /** @var array<string,array{target:string,paths:array<string,string>}> */
    private array $aliases = [];

    /** @var array<string,string> */
    private array $localeLanguages = [];

    /** @var array<string,array<string,string>> */
    private array $localizedExact = [];

    /** @var array<string,array<string,string>> */
    private array $localizedAliases = [];

    /** @var list<string> */
    private array $tailRoutes = [];

    /**
     * @param array<string,mixed> $config
     * @param list<string> $supportedLocales
     */
    private function __construct(
        array $config,
        array $supportedLocales,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        if (($config['contract'] ?? null) !== self::CONTRACT) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_CONTRACT_INVALID'
            );
        }

        if (($config['unicode_normalization'] ?? null) !== 'NFC') {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_UNICODE_POLICY_INVALID'
            );
        }

        if (($config['legacy_canonical_routes_accepted'] ?? null) !== true) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_LEGACY_POLICY_INVALID'
            );
        }

        if (($config['regional_policy'] ?? null)
            !== 'inherit-base-language') {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_REGIONAL_POLICY_INVALID'
            );
        }

        $this->initializeLocales($supportedLocales);
        $this->initializeRoutes($config);
        $this->initializeAliases($config);
        $this->validateCollisions();
    }

    public static function fromFile(
        string $configFile,
        array $supportedLocales,
        ?ProfilerInterface $profiler = null,
        ?string $parentSpanId = null
    ): LocalizedRouteResolverInterface {
        try {
            $config = StructuredFileLoader::instance()->read($configFile);
        } catch (\Throwable $cause) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        return new self(
            $config,
            $supportedLocales,
            $profiler,
            $parentSpanId
        );
    }

    public function resolve(string $locale, string $publicPath): string
    {
        $language = $this->language($locale);
        $publicPath = $this->validateOpaquePath(
            trim($publicPath, '/')
        );

        if ($publicPath === '') {
            return '';
        }

        foreach ($this->tailRoutes as $canonical) {
            $localizedPrefix = $this->routes[$canonical]['paths'][$language];
            $localizedSegments = explode('/', $localizedPrefix);
            $publicSegments = explode('/', $publicPath);

            if (count($publicSegments) > count($localizedSegments)) {
                $candidatePrefix = implode(
                    '/',
                    array_slice(
                        $publicSegments,
                        0,
                        count($localizedSegments)
                    )
                );
                $candidatePrefix = $this->normalizeInputPath(
                    $candidatePrefix
                );

                if ($candidatePrefix === $localizedPrefix) {
                    $tail = implode(
                        '/',
                        array_slice(
                            $publicSegments,
                            count($localizedSegments)
                        )
                    );
                    $tail = $this->validateOpaquePath($tail);
                    $resolved = $canonical . '/' . $tail;
                    $this->recordResolve(
                        $locale,
                        $publicPath,
                        $resolved,
                        false
                    );

                    return $resolved;
                }
            }

            if (str_starts_with($publicPath, $canonical . '/')) {
                $tail = substr(
                    $publicPath,
                    strlen($canonical) + 1
                );
                $tail = $this->validateOpaquePath($tail);
                $resolved = $canonical . '/' . $tail;
                $this->recordResolve(
                    $locale,
                    $publicPath,
                    $resolved,
                    true
                );

                return $resolved;
            }
        }

        $normalizedPublicPath = $this->normalizeInputPath(
            $publicPath
        );

        if (isset(
            $this->localizedExact[$language][$normalizedPublicPath]
        )) {
            $canonical =
                $this->localizedExact[$language][$normalizedPublicPath];
            $this->recordResolve(
                $locale,
                $publicPath,
                $canonical,
                false
            );

            return $canonical;
        }

        if (isset(
            $this->localizedAliases[$language][$normalizedPublicPath]
        )) {
            $canonical =
                $this->localizedAliases[$language][$normalizedPublicPath];
            $this->recordResolve(
                $locale,
                $publicPath,
                $canonical,
                true
            );

            return $canonical;
        }

        if (isset($this->routes[$normalizedPublicPath])) {
            $this->recordResolve(
                $locale,
                $publicPath,
                $normalizedPublicPath,
                true
            );

            return $normalizedPublicPath;
        }

        if (isset($this->aliases[$normalizedPublicPath])) {
            $canonical =
                $this->aliases[$normalizedPublicPath]['target'];
            $this->recordResolve(
                $locale,
                $publicPath,
                $canonical,
                true
            );

            return $canonical;
        }

        $this->recordResolve(
            $locale,
            $publicPath,
            $normalizedPublicPath,
            true
        );

        return $normalizedPublicPath;
    }

    public function localize(string $locale, string $canonicalPath): string
    {
        $language = $this->language($locale);
        $canonicalPath = $this->validateOpaquePath(
            trim($canonicalPath, '/')
        );

        foreach ($this->tailRoutes as $canonical) {
            if (!str_starts_with($canonicalPath, $canonical . '/')) {
                continue;
            }

            $tail = substr(
                $canonicalPath,
                strlen($canonical) + 1
            );
            $tail = $this->validateOpaquePath($tail);

            return $this->routes[$canonical]['paths'][$language]
                . '/'
                . $tail;
        }

        $canonicalPath = $this->normalizeInputPath($canonicalPath);

        if (isset($this->routes[$canonicalPath])) {
            return $this->routes[$canonicalPath]['paths'][$language];
        }

        throw new RuntimeException(
            'OPUS_LOCALIZED_ROUTE_CANONICAL_UNKNOWN'
        );
    }

    public function url(
        string $basePath,
        string $locale,
        string $canonicalPath,
        array $query = []
    ): string {
        $localized = $this->localize($locale, $canonicalPath);
        $segments = array_values(array_filter(
            explode('/', $localized),
            static fn (string $segment): bool => $segment !== ''
        ));
        array_unshift($segments, $locale);

        return (new UrlBuilder($basePath))->build(
            $segments,
            $query
        );
    }

    public function isLocalized(string $locale, string $publicPath): bool
    {
        $publicPath = $this->validateOpaquePath(
            trim($publicPath, '/')
        );
        $resolved = $this->resolve($locale, $publicPath);

        try {
            return $this->localize($locale, $resolved) === $publicPath;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param list<string> $supportedLocales
     */
    private function initializeLocales(array $supportedLocales): void
    {
        if ($supportedLocales === []) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_SUPPORTED_LOCALES_EMPTY'
            );
        }

        foreach ($supportedLocales as $locale) {
            if (!is_string($locale) || trim($locale) === '') {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_LOCALE_INVALID'
                );
            }

            $normalized = new Locale($locale);
            $this->localeLanguages[$normalized->value] =
                $normalized->language;
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    private function initializeRoutes(array $config): void
    {
        $routes = is_array($config['routes'] ?? null)
            ? $config['routes']
            : [];

        if ($routes === []) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_ROUTES_EMPTY'
            );
        }

        foreach ($routes as $canonical => $definition) {
            if (!is_string($canonical) || !is_array($definition)) {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_DEFINITION_INVALID'
                );
            }

            $canonical = $this->normalizeCanonicalRoute($canonical);
            $tail = ($definition['tail'] ?? false) === true;
            $paths = $this->languagePaths(
                is_array($definition['paths'] ?? null)
                    ? $definition['paths']
                    : []
            );

            $this->routes[$canonical] = [
                'tail' => $tail,
                'paths' => $paths,
            ];

            if ($tail) {
                $this->tailRoutes[] = $canonical;
            }

            foreach ($paths as $language => $path) {
                $this->localizedExact[$language][$path] = $canonical;
            }
        }

        usort(
            $this->tailRoutes,
            static fn (string $left, string $right): int =>
                strlen($right) <=> strlen($left)
        );
    }

    /**
     * @param array<string,mixed> $config
     */
    private function initializeAliases(array $config): void
    {
        $aliases = is_array($config['aliases'] ?? null)
            ? $config['aliases']
            : [];

        foreach ($aliases as $alias => $definition) {
            if (!is_string($alias) || !is_array($definition)) {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_ALIAS_INVALID'
                );
            }

            $alias = $this->normalizeCanonicalRoute($alias);
            $target = $this->normalizeCanonicalRoute(
                (string) ($definition['target'] ?? '')
            );
            $paths = $this->languagePaths(
                is_array($definition['paths'] ?? null)
                    ? $definition['paths']
                    : []
            );

            $this->aliases[$alias] = [
                'target' => $target,
                'paths' => $paths,
            ];

            foreach ($paths as $language => $path) {
                $this->localizedAliases[$language][$path] = $target;
            }
        }
    }

    private function validateCollisions(): void
    {
        foreach ($this->localeLanguages as $language) {
            $seen = [];

            foreach ($this->routes as $canonical => $definition) {
                $path = $definition['paths'][$language];
                if (isset($seen[$path]) && $seen[$path] !== $canonical) {
                    throw new RuntimeException(
                        'OPUS_LOCALIZED_ROUTE_COLLISION'
                    );
                }
                $seen[$path] = $canonical;
            }

            foreach ($this->aliases as $definition) {
                $path = $definition['paths'][$language];
                $target = $definition['target'];
                if (isset($seen[$path]) && $seen[$path] !== $target) {
                    throw new RuntimeException(
                        'OPUS_LOCALIZED_ROUTE_ALIAS_COLLISION'
                    );
                }
                $seen[$path] = $target;
            }
        }
    }

    /**
     * @param array<string,mixed> $paths
     * @return array<string,string>
     */
    private function languagePaths(array $paths): array
    {
        $requiredLanguages = array_values(array_unique(
            array_values($this->localeLanguages)
        ));
        $normalized = [];

        foreach ($requiredLanguages as $language) {
            $path = $paths[$language] ?? null;
            if (!is_string($path) || trim($path) === '') {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_LANGUAGE_PATH_MISSING'
                );
            }
            $normalized[$language] =
                $this->normalizeConfiguredPath($path);
        }

        return $normalized;
    }

    private function language(string $locale): string
    {
        $normalized = (new Locale($locale))->value;

        if (!isset($this->localeLanguages[$normalized])) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_LOCALE_UNSUPPORTED'
            );
        }

        return $this->localeLanguages[$normalized];
    }

    private function normalizeCanonicalRoute(string $route): string
    {
        $route = trim($route, '/');

        if ($route === ''
            || preg_match(
                '~^[a-z][a-z0-9._-]*(?:/[a-z][a-z0-9._-]*)*$~D',
                $route
            ) !== 1) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_CANONICAL_INVALID'
            );
        }

        return $route;
    }

    private function normalizeConfiguredPath(string $path): string
    {
        $original = $path;
        $normalized = $this->normalizeUnicode($path);

        if ($normalized !== $original) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_CONFIG_NFC_INVALID'
            );
        }

        return $this->validatePath($normalized);
    }

    private function normalizeInputPath(string $path): string
    {
        return $this->validatePath(
            $this->normalizeUnicode(trim($path, '/'))
        );
    }

    private function validateOpaquePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || preg_match('//u', $path) !== 1) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_PATH_INVALID'
            );
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || $segment !== trim($segment)) {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_SEGMENT_INVALID'
                );
            }
        }

        return $path;
    }

    private function validatePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || preg_match('//u', $path) !== 1) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_PATH_INVALID'
            );
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || $segment !== trim($segment)) {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_SEGMENT_INVALID'
                );
            }
        }

        return $path;
    }

    private function normalizeUnicode(string $value): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_UTF8_INVALID'
            );
        }

        if (class_exists('\\Normalizer')) {
            $normalized = \Normalizer::normalize(
                $value,
                \Normalizer::FORM_C
            );

            if (!is_string($normalized)) {
                throw new RuntimeException(
                    'OPUS_LOCALIZED_ROUTE_NFC_FAILED'
                );
            }

            return $normalized;
        }

        if (preg_match('/\p{M}/u', $value) === 1) {
            throw new RuntimeException(
                'OPUS_LOCALIZED_ROUTE_NFC_NORMALIZER_UNAVAILABLE'
            );
        }

        return $value;
    }

    private function recordResolve(
        string $locale,
        string $publicPath,
        string $canonicalPath,
        bool $legacy
    ): void {
        if (!$this->profiler instanceof ProfilerInterface) {
            return;
        }

        $this->profiler->event(
            'routing',
            'localized.route.resolved',
            [
                'locale' => $locale,
                'public_path' => $publicPath,
                'canonical_path' => $canonicalPath,
                'legacy' => $legacy,
            ],
            'success',
            null,
            $this->parentSpanId
        );
    }
}

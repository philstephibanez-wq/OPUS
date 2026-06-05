<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Represent a legacy ASAP package as explicit immutable metadata.
 *
 * Contract:
 *   Data object only. No filesystem scan and no implicit package fallback.
 *
 * Since:
 *   P112O
 */
final class Package
{
    /**
     * @param string[] $languages
     * @param array<string,string> $routes
     * @param array<string,mixed> $content
     */
    public function __construct(
        private readonly string $id,
        private readonly string $rootDir,
        private readonly array $languages = [],
        private readonly array $routes = [],
        private readonly array $content = []
    ) {
        if (trim($this->id) === '') {
            throw Exception::because('ASAP_PACKAGE_ID_EMPTY');
        }

        if (trim($this->rootDir) === '') {
            throw Exception::because('ASAP_PACKAGE_ROOT_EMPTY', $this->id);
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    public function hasLanguage(string $language): bool
    {
        return in_array(strtolower($language), array_map('strtolower', $this->languages), true);
    }

    /** @return array<string,string> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function content(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->content;
        }

        if (!array_key_exists($key, $this->content)) {
            throw Exception::because('ASAP_PACKAGE_CONTENT_KEY_MISSING', $this->id . '::' . $key);
        }

        return $this->content[$key];
    }
}

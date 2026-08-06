<?php
declare(strict_types=1);

namespace Opus\Api\Rest;

interface RestResourceCatalogInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<int,mixed> $resources */
    public static function fromArray(
        array $resources,
        string $basePath = '/api/v1'
    ): self;

    public static function fromFile(string $catalogFile): self;

    public function basePath(): string;

    public function fingerprint(): string;

    /** @return list<array<string,mixed>> */
    public function routes(): array;

    /**
     * @return array{
     *   0:?array<string,mixed>,
     *   1:array<string,string>,
     *   2:list<string>
     * }
     */
    public function resolve(string $method, string $path): array;

    /** @return array<string,mixed> */
    public function assertRequest(string $method, string $path): array;

    /** @param array<string,mixed> $route */
    public function successStatus(array $route): int;

    /**
     * @param array<string,mixed> $route
     * @param array<string,mixed> $parameters
     */
    public function location(array $route, array $parameters): string;

    public function assertPeerFingerprint(string $fingerprint): void;
}

<?php
declare(strict_types=1);

namespace Opus\Console\Service;

interface SiteCommandServiceInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function create(string $siteId, bool $write): array;
    /** @return array<string,mixed> */
    public function validate(string $siteId): array;
    /** @return array<string,mixed> */
    public function addLanguage(string $siteId, string $locale, bool $write): array;
    /** @return array<string,mixed> */
    public function listRoutes(string $siteId): array;
    /** @return array<string,mixed> */
    public function createPage(string $siteId, string $moduleId, string $pageId, string $path, string $title, bool $write): array;
    /** @return array<string,mixed> */
    public function createRubric(string $siteId, string $moduleId, string $path, string $title, bool $write): array;
    public function serve(string $siteId, string $host, int $port): int;
}

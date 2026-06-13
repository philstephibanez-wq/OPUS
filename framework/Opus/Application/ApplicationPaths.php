<?php

declare(strict_types=1);

namespace Opus\Application;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: APPLICATION
 *   role: Class ApplicationPaths belongs to the APPLICATION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the APPLICATION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - application-overview
 *   diagrams:
 *     - application-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare the filesystem boundaries of one Opus application.
 *
 * Responsibility:
 *   Hold official root paths consumed by SiteResolver, Router and Renderer.
 *
 * Contract:
 *   Paths must exist explicitly. No implicit path fallback.
 *
 * Since:
 *   P112D1
 */
final class ApplicationPaths
{
    public readonly string $appRoot;
    public readonly string $sitesRoot;
    public readonly string $contentRoot;
    public readonly string $templatesRoot;
    public readonly string $cacheRoot;
    public readonly string $siteId;

    public function __construct(string $appRoot, string $siteId)
    {
        $root = rtrim(str_replace('\\', '/', $appRoot), '/');

        if ($root === '' || !is_dir($root)) {
            throw ContractException::because('OPUS_APP_ROOT_INVALID', $appRoot);
        }

        if (trim($siteId) === '') {
            throw ContractException::because('OPUS_SITE_ID_EMPTY');
        }

        $this->appRoot = $root;
        $this->siteId = $siteId;
        $this->sitesRoot = $root . '/sites';
        $this->contentRoot = $root . '/content/markdown';
        $this->templatesRoot = $root . '/application/reference/templates';
        $this->cacheRoot = $root . '/var/cache/twig';

        foreach ([$this->sitesRoot, $this->contentRoot, $this->templatesRoot] as $path) {
            if (!is_dir($path)) {
                throw ContractException::because('OPUS_REQUIRED_PATH_MISSING', $path);
            }
        }
    }
}

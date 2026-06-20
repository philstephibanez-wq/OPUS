<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Creates a new page template and route in an existing generated OPUS module.
 *
 * Public contract:
 * - requires --write to mutate the site;
 * - target module must already exist;
 * - writes one .score page template;
 * - appends one route in application/config/routes.json;
 * - updates i18n resources with starter keys;
 * - fails on duplicate route id, duplicate path, or existing template.
 */
final class CreatePageCommand implements OpusConsoleCommandInterface
{
    private readonly SiteScaffoldCommandSupport $support;

    public function __construct(private readonly string $opusRoot)
    {
        $this->support = new SiteScaffoldCommandSupport($opusRoot);
    }

    public function name(): string
    {
        return 'create:page';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $positionals = $this->support->positionalArguments($arguments);
        if (count($positionals) !== 4) {
            throw new OpusConsoleException('OPUS_CREATE_PAGE_USAGE: create:page <site-id> <ModuleId> <page-id> <path> [--title <title>] --write');
        }

        [$siteId, $moduleId, $pageId, $path] = $positionals;
        $title = $this->support->optionValue($arguments, '--title', $moduleId . ' ' . $pageId) ?? ($moduleId . ' ' . $pageId);
        $write = $this->support->hasFlag($arguments, '--write');
        $this->support->requireWrite($write, 'OPUS_CREATE_PAGE_PLAN: ' . $siteId . '/' . $moduleId . '/' . $pageId . ' -> ' . $path);

        $siteRoot = $this->support->siteRoot($siteId);
        $this->support->createPage($siteRoot, $moduleId, $pageId, $path, $title);

        echo 'OPUS_CREATE_PAGE_WRITTEN: ' . $siteId . '/' . $moduleId . '/' . $pageId . ' ' . $path . "\n";
        return 0;
    }
}

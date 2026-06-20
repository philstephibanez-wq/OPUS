<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Creates a new rubric as a module plus default index route.
 *
 * Public contract:
 * - requires --write to mutate the site;
 * - writes a module scaffold;
 * - appends the default route <module>.index;
 * - updates i18n resources with starter keys;
 * - fails loudly on duplicate module or route path.
 */
final class CreateRubricCommand implements OpusConsoleCommandInterface
{
    private readonly SiteScaffoldCommandSupport $support;

    public function __construct(private readonly string $opusRoot)
    {
        $this->support = new SiteScaffoldCommandSupport($opusRoot);
    }

    public function name(): string
    {
        return 'create:rubric';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $positionals = $this->support->positionalArguments($arguments);
        if (count($positionals) !== 3) {
            throw new OpusConsoleException('OPUS_CREATE_RUBRIC_USAGE: create:rubric <site-id> <ModuleId> <path> [--title <title>] --write');
        }

        [$siteId, $moduleId, $path] = $positionals;
        $title = $this->support->optionValue($arguments, '--title', $moduleId) ?? $moduleId;
        $write = $this->support->hasFlag($arguments, '--write');
        $this->support->requireWrite($write, 'OPUS_CREATE_RUBRIC_PLAN: ' . $siteId . '/' . $moduleId . ' -> ' . $path);

        $siteRoot = $this->support->siteRoot($siteId);
        $this->support->assertRoutePath($path, 'OPUS_CREATE_RUBRIC_INVALID_ROUTE_PATH');
        $this->support->createRubric($siteRoot, $moduleId, $path, $title);

        echo 'OPUS_CREATE_RUBRIC_WRITTEN: ' . $siteId . '/' . $moduleId . ' ' . $path . "\n";
        return 0;
    }
}

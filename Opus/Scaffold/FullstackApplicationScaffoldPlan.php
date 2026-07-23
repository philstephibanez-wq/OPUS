<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Compatibility adapter preserving the historic fullstack scaffold entrypoint.
 *
 * The canonical OPUS application tree is defined only by SiteScaffoldPlan.
 * This adapter contains no second architecture and delegates every entry.
 */
final class FullstackApplicationScaffoldPlan implements
    ScaffoldPlanInterface,
    FullstackApplicationScaffoldPlanInterface
{
    private function __construct(
        private readonly SiteScaffoldPlan $canonicalPlan
    ) {
    }

    public static function forApplication(string $applicationId): self
    {
        return new self(SiteScaffoldPlan::forSite($applicationId));
    }

    public function rootRelativePath(): string
    {
        return $this->canonicalPlan->rootRelativePath();
    }

    /** @return list<ScaffoldEntry> */
    public function entries(): array
    {
        return $this->canonicalPlan->entries();
    }
}

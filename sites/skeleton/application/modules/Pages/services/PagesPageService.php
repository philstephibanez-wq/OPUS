<?php
declare(strict_types=1);

namespace OpusSite\Skeleton\Pages\Service;

/**
 * Generated Pages service skeleton.
 *
 * Contract:
 * - service prepares/validates module data;
 * - service does not render HTML;
 * - service returns data for a view-model or response model.
 */
final class PagesPageService
{
    /**
     * @return array<string, string>
     */
    public function loadStarterData(): array
    {
        return [
            'status' => 'starter',
        ];
    }
}
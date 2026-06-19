<?php
declare(strict_types=1);

namespace OpusSite\Skeleton\Home\Service;

/**
 * Generated Home service skeleton.
 *
 * Contract:
 * - service prepares/validates module data;
 * - service does not render HTML;
 * - service returns data for a view-model or response model.
 */
final class HomePageService
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
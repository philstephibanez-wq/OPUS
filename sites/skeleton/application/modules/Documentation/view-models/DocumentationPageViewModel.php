<?php
declare(strict_types=1);

namespace OpusSite\Skeleton\Documentation\ViewModel;

/**
 * Generated Documentation view-model skeleton.
 *
 * Contract:
 * - view-model contains render-ready state;
 * - no business computation here;
 * - no HTML concatenation here.
 */
final class DocumentationPageViewModel
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
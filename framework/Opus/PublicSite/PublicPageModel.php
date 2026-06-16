<?php

declare(strict_types=1);

namespace Opus\PublicSite;

final class PublicPageModel
{
    public function __construct(
        private readonly string $title,
        private readonly string $content
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}

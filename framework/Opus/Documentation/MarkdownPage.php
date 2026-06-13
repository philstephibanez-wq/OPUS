<?php

declare(strict_types=1);

namespace Opus\Documentation;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: DOCUMENTATION
 *   role: Class MarkdownPage belongs to the DOCUMENTATION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DOCUMENTATION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - documentation-overview
 *   diagrams:
 *     - documentation-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry one Reference Book source page.
 *
 * Responsibility:
 *   Store validated title, slug and Markdown body.
 *
 * Contract:
 *   Data only. No HTML rendering and no filesystem access.
 *
 * Since:
 *   P112D1
 */
final class MarkdownPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $markdown
    ) {
        if (trim($this->slug) === '') {
            throw ContractException::because('OPUS_MARKDOWN_PAGE_SLUG_EMPTY');
        }

        if (trim($this->title) === '') {
            throw ContractException::because('OPUS_MARKDOWN_PAGE_TITLE_EMPTY');
        }
    }
}

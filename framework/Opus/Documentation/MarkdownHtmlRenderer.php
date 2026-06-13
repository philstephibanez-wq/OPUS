<?php

declare(strict_types=1);

namespace Opus\Documentation;

/*
 * OPUS_REFBOOK:
 *   domain: DOCUMENTATION
 *   role: Class MarkdownHtmlRenderer belongs to the DOCUMENTATION Opus framework domain.
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
 * PUBLIC RENDERER
 *
 * Role:
 *   Convert trusted local Reference Book Markdown into minimal HTML.
 *
 * Responsibility:
 *   Provide a deterministic Markdown subset for the documentation site.
 *
 * Contract:
 *   Representation only. It does not load files, decide routes or change state.
 *
 * Since:
 *   P112D1
 */
final class MarkdownHtmlRenderer
{
    /**
     * PUBLIC API
     *
     * @param string $markdown Markdown source.
     *
     * @return string Rendered HTML.
     */
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $html = [];
        $inCode = false;
        $code = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '```')) {
                if ($inCode) {
                    $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
                    $code = [];
                    $inCode = false;
                } else {
                    $inCode = true;
                }

                continue;
            }

            if ($inCode) {
                $code[] = $line;
                continue;
            }

            $trim = trim($line);

            if ($trim === '') {
                continue;
            }

            if (str_starts_with($trim, '# ')) {
                $html[] = '<h1>' . $this->inline(substr($trim, 2)) . '</h1>';
                continue;
            }

            if (str_starts_with($trim, '## ')) {
                $html[] = '<h2>' . $this->inline(substr($trim, 3)) . '</h2>';
                continue;
            }

            if (str_starts_with($trim, '- ')) {
                $html[] = '<p class="opus-ref-bullet">â€¢ ' . $this->inline(substr($trim, 2)) . '</p>';
                continue;
            }

            $html[] = '<p>' . $this->inline($trim) . '</p>';
        }

        if ($inCode) {
            $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
        }

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

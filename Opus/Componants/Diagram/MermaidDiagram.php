<?php
declare(strict_types=1);

namespace Opus\Componants\Diagram;

final class MermaidDiagram
{
    public function __construct(
        private string $id,
        private string $source
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $this->id) !== 1) {
            throw new \InvalidArgumentException('OPUS_MERMAID_ID_INVALID');
        }

        if (trim($this->source) === '') {
            throw new \InvalidArgumentException('OPUS_MERMAID_SOURCE_REQUIRED');
        }
    }

    public function render(): string
    {
        $id = htmlspecialchars($this->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $source = htmlspecialchars($this->source, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div id="' . $id . '" class="opus-mermaid-diagram" data-opus-mermaid="true">'
            . '<script type="text/plain">' . $source . '</script>'
            . '</div>';
    }
}

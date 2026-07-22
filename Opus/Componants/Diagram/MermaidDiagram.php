<?php
declare(strict_types=1);

namespace Opus\Componants\Diagram;

final class MermaidDiagram implements MermaidDiagramInterface
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
        $id = htmlspecialchars(
            $this->id,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $payload = json_encode(
            ['source' => $this->source],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR
        );

        return '<div id="' . $id . '"'
            . ' class="opus-mermaid-diagram"'
            . ' data-opus-mermaid="true"'
            . ' data-opus-mermaid-source-contract="OPUS_MERMAID_SOURCE_JSON_V1">'
            . '<script type="application/json" data-opus-mermaid-source>'
            . $payload
            . '</script>'
            . '</div>';
    }
}

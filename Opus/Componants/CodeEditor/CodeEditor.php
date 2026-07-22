<?php
declare(strict_types=1);

namespace Opus\Componants\CodeEditor;

final class CodeEditor implements CodeEditorInterface
{
    public function __construct(
        private string $id,
        private string $value = '',
        private string $path = '',
        private bool $readOnly = true
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $this->id) !== 1) {
            throw new \InvalidArgumentException('OPUS_CODE_EDITOR_ID_INVALID');
        }
    }

    public function render(): string
    {
        $id = htmlspecialchars($this->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = htmlspecialchars($this->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $path = htmlspecialchars($this->path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div id="' . $id . '" class="opus-code-editor"'
            . ' data-opus-code-editor="true"'
            . ' data-path="' . $path . '"'
            . ' data-read-only="' . ($this->readOnly ? 'true' : 'false') . '">'
            . '<textarea hidden>' . $value . '</textarea>'
            . '</div>';
    }
}

<?php

declare(strict_types=1);

namespace ASAP\LINK;

/**
 * PUBLIC LEGACY-ALIGNED LINK
 *
 * Role:
 *   Preserve the original ASAP `LINK\Link` object.
 *
 * Responsibility:
 *   Carry one safe link declaration and the remaining small legacy modifiers.
 *
 * Contract:
 *   Link renders only a simple anchor string. It does not route, authorize or
 *   fetch state.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112P1 restores __toString/changeClass/changeId/getBlock/getMode.
 */
final class Link
{
    private string $class = '';
    private string $id = '';

    public function __construct(
        public string $label,
        public string $href,
        private string $block = '',
        private string $mode = ''
    ) {
        if (trim($this->label) === '' || trim($this->href) === '') {
            throw new \InvalidArgumentException('ASAP_LINK_INVALID');
        }
    }

    public function __toString(): string
    {
        $attributes = [
            'href' => $this->href,
        ];

        if ($this->id !== '') {
            $attributes['id'] = $this->id;
        }

        if ($this->class !== '') {
            $attributes['class'] = $this->class;
        }

        $html = '<a';

        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $html . '>' . htmlspecialchars($this->label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>';
    }

    public function changeClass(string $class): self
    {
        $this->class = trim($class);

        return $this;
    }

    public function changeId(string $id): self
    {
        $this->id = trim($id);

        return $this;
    }

    public function getBlock(): string
    {
        return $this->block;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}

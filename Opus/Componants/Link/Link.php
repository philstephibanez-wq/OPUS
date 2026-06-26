<?php

declare(strict_types=1);

namespace Opus\Link;

/*
 * OPUS_REFBOOK:
 *   domain: LINK
 *   role: Class Link belongs to the LINK Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LINK domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - link-overview
 *   diagrams:
 *     - link-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED LINK
 *
 * Role:
 *   Preserve the original Opus `LINK\Link` object.
 *
 * Responsibility:
 *   Carry one safe link declaration and the remaining small modifiers.
 *
 * Contract:
 *   Link renders only a simple anchor string. It does not route, authorize or
 *   fetch state.
 *
 * Since:
 *   P112D4C
 *
 * OPUS compatibility:
 *   P112P1 restores __toString/changeClass/changeId/getBlock/getMode.
 */
final class Link
 implements LinkInterface {
    private string $class = '';
    private string $id = '';

    public function __construct(
        public string $label,
        public string $href,
        private string $block = '',
        private string $mode = ''
    ) {
        if (trim($this->label) === '' || trim($this->href) === '') {
            throw new \InvalidArgumentException('OPUS_LINK_INVALID');
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

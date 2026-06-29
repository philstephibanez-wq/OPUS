<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Backoffice-facing declaration object for future LSTSAR Manager screens.
 */
final class LstsarBackofficeDeclaration
{
    private LstsarConfig $config;
    /** @var list<string> */
    private array $editableSections;

    /** @param list<string> $editableSections */
    public function __construct(LstsarConfig $config, array $editableSections = [])
    {
        foreach ($editableSections as $section) {
            if (!in_array($section, ['source', 'destination', 'mapping', 'security', 'transform', 'archive', 'report'], true)) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_BACKOFFICE_SECTION_UNSUPPORTED: ' . $section);
            }
        }
        $this->config = $config;
        $this->editableSections = array_values($editableSections);
    }

    public function config(): LstsarConfig
    {
        return $this->config;
    }

    /** @return list<string> */
    public function editableSections(): array
    {
        return $this->editableSections;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => 'OPUS_LSTSAR_BACKOFFICE_DECLARATION_V1',
            'config' => $this->config->toArray(),
            'editable_sections' => $this->editableSections,
            'manager_target' => 'P7_LSTSAR_MANAGER_PACKAGE_CORE',
        ];
    }
}

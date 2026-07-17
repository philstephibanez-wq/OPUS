<?php
declare(strict_types=1);

namespace Opus\I18n;

use RuntimeException;

/**
 * Rejects raw user-visible text in UI configuration trees.
 *
 * Visible text fields must use a sibling key name ending in `_key` and contain
 * a valid I18nKey-compatible identifier. User data remains allowed under
 * explicit data fields such as name, value, content, path and id.
 */
final class UiTextContractValidator
{
    public const CONTRACT = 'OPUS_I18N_STRICT_UI_CONTRACT_V1';

    /** @var list<string> */
    private const RAW_UI_FIELDS = [
        'title',
        'label',
        'summary',
        'description',
        'message',
        'placeholder',
        'help',
        'caption',
        'tooltip',
        'badge',
        'confirm',
    ];

    /**
     * @param array<mixed> $configuration
     */
    public function validate(array $configuration): void
    {
        $this->validateNode($configuration, '$');
    }

    /**
     * @param array<mixed> $node
     */
    private function validateNode(array $node, string $path): void
    {
        foreach ($node as $key => $value) {
            $field = (string) $key;
            $currentPath = $path . '.' . $field;

            if (in_array($field, self::RAW_UI_FIELDS, true) && is_string($value) && trim($value) !== '') {
                throw new RuntimeException('OPUS_I18N_RAW_UI_TEXT_FORBIDDEN:' . $currentPath);
            }

            if (str_ends_with($field, '_key')) {
                if (!is_string($value)) {
                    throw new RuntimeException('OPUS_I18N_KEY_TYPE_INVALID:' . $currentPath);
                }
                try {
                    new I18nKey($value);
                } catch (\InvalidArgumentException) {
                    throw new RuntimeException('OPUS_I18N_KEY_INVALID:' . $currentPath);
                }
            }

            if (is_array($value)) {
                $this->validateNode($value, $currentPath);
            }
        }
    }
}

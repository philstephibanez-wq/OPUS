<?php
declare(strict_types=1);

namespace Opus\I18n;

use InvalidArgumentException;

/**
 * Immutable validated translation key.
 *
 * UI contracts must carry this type instead of arbitrary visible strings.
 */
final readonly class I18nKey
{
    public const CONTRACT = 'OPUS_I18N_KEY_V1';

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException('OPUS_I18N_KEY_INVALID');
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

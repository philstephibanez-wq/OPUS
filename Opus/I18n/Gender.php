<?php
declare(strict_types=1);

namespace Opus\I18n;

enum Gender: string
{
    case Masculine = 'masculine';
    case Feminine = 'feminine';
    case Neuter = 'neuter';

    public static function fromInput(string $value): self
    {
        return match (strtolower(trim($value))) {
            'masculine', 'male', 'm' => self::Masculine,
            'feminine', 'female', 'f' => self::Feminine,
            'neuter', 'neutral', 'n' => self::Neuter,
            default => throw TranslationException::because(
                'OPUS_I18N_GENDER_INVALID',
                $value
            ),
        };
    }
}

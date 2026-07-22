<?php
declare(strict_types=1);

namespace Opus\I18n;

use Stringable;

final class MessageInterpolator implements MessageInterpolatorInterface
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function interpolate(
        string $template,
        array $parameters,
        string $key
    ): string {
        preg_match_all(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            $template,
            $matches
        );

        $required = array_values(array_unique($matches[1] ?? []));

        foreach ($required as $name) {
            if (!array_key_exists($name, $parameters)) {
                throw TranslationException::because(
                    'OPUS_I18N_PARAMETER_MISSING',
                    $key . ':' . $name
                );
            }

            $template = str_replace(
                '{' . $name . '}',
                $this->scalar($parameters[$name], $key, $name),
                $template
            );
        }

        if (
            preg_match(
                '/\{[A-Za-z_][A-Za-z0-9_]*\}/',
                $template
            ) === 1
        ) {
            throw TranslationException::because(
                'OPUS_I18N_PARAMETER_UNRESOLVED',
                $key
            );
        }

        return $template;
    }

    private function scalar(
        mixed $value,
        string $key,
        string $name
    ): string {
        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
        ) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw TranslationException::because(
            'OPUS_I18N_PARAMETER_TYPE_INVALID',
            $key . ':' . $name
        );
    }
}

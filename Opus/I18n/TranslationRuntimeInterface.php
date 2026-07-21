<?php
declare(strict_types=1);

namespace Opus\I18n;

interface TranslationRuntimeInterface
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function translate(
        string $key,
        array $parameters = [],
        int|float|null $count = null,
        Gender|string|null $gender = null
    ): string;

    public function locale(): Locale;

    public function module(): string;
}

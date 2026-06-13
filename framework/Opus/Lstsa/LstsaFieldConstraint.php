<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaFieldConstraint belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC Lstsa FIELD CONSTRAINT
 *
 * Role:
 *   Describe and validate a declared field at an Lstsa boundary.
 *
 * Contract:
 *   Input and output constraints are explicit. Length and byte size are first
 *   class checks, not afterthoughts.
 */
final class LstsaFieldConstraint
{
    /**
     * @param list<string> $enum
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required = false,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?int $exactLength = null,
        public readonly ?int $maxBytes = null,
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly ?string $regex = null,
        public readonly array $enum = []
    ) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $this->name)) {
            throw LstsaException::because('OPUS_Lstsa_FIELD_NAME_INVALID', $this->name);
        }

        if (!in_array($this->type, self::supportedTypes(), true)) {
            throw LstsaException::because('OPUS_Lstsa_FIELD_TYPE_UNSUPPORTED', $this->type);
        }

        foreach (['minLength' => $this->minLength, 'maxLength' => $this->maxLength, 'exactLength' => $this->exactLength, 'maxBytes' => $this->maxBytes] as $label => $value) {
            if ($value !== null && $value < 0) {
                throw LstsaException::because('OPUS_Lstsa_FIELD_CONSTRAINT_NEGATIVE', $this->name . '.' . $label);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return ['string', 'email', 'int', 'integer', 'float', 'number', 'bool', 'boolean', 'datetime', 'date', 'json'];
    }

    public static function fromXml(SimpleXMLElement $xml, string $nameAttribute = 'name'): self
    {
        $name = trim((string) ($xml[$nameAttribute] ?? ''));
        if ($name === '' && $nameAttribute !== 'name') {
            $name = trim((string) ($xml['name'] ?? ''));
        }

        $type = strtolower(trim((string) ($xml['type'] ?? 'string')));
        $enumRaw = trim((string) ($xml['enum'] ?? $xml['values'] ?? ''));
        $enum = $enumRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $enumRaw)), static fn (string $v): bool => $v !== ''));

        return new self(
            $name,
            $type,
            self::boolAttr($xml, 'required'),
            self::intAttr($xml, 'min_length'),
            self::intAttr($xml, 'max_length'),
            self::intAttr($xml, 'exact_length'),
            self::intAttr($xml, 'max_bytes'),
            self::floatAttr($xml, 'min'),
            self::floatAttr($xml, 'max'),
            self::stringAttr($xml, 'regex'),
            $enum
        );
    }

    /**
     * @return list<string>
     */
    public function validate(mixed $value, string $phase): array
    {
        $errors = [];

        if ($value === null || $value === '') {
            if ($this->required) {
                $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_REQUIRED';
            }

            return $errors;
        }

        if (!$this->matchesType($value)) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_TYPE_INVALID';
            return $errors;
        }

        $asString = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $asString = $asString === false ? '' : $asString;
        $length = function_exists('mb_strlen') ? mb_strlen($asString, 'UTF-8') : strlen($asString);
        $bytes = strlen($asString);

        if ($this->exactLength !== null && $length !== $this->exactLength) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_EXACT_LENGTH_INVALID';
        }

        if ($this->minLength !== null && $length < $this->minLength) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_MIN_LENGTH_INVALID';
        }

        if ($this->maxLength !== null && $length > $this->maxLength) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_MAX_LENGTH_INVALID';
        }

        if ($this->maxBytes !== null && $bytes > $this->maxBytes) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_MAX_BYTES_INVALID';
        }

        if (($this->min !== null || $this->max !== null) && is_numeric($value)) {
            $number = (float) $value;
            if ($this->min !== null && $number < $this->min) {
                $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_MIN_INVALID';
            }
            if ($this->max !== null && $number > $this->max) {
                $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_MAX_INVALID';
            }
        }

        if ($this->regex !== null && @preg_match($this->regex, $asString) !== 1) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_REGEX_INVALID';
        }

        if ($this->enum !== [] && !in_array($asString, $this->enum, true)) {
            $errors[] = $phase . ':' . $this->name . ':OPUS_Lstsa_FIELD_ENUM_INVALID';
        }

        return $errors;
    }

    private function matchesType(mixed $value): bool
    {
        return match ($this->type) {
            'string' => is_scalar($value),
            'email' => is_scalar($value) && filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false,
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'float', 'number' => is_numeric($value),
            'bool', 'boolean' => is_bool($value) || in_array(strtolower((string) $value), ['0', '1', 'true', 'false', 'yes', 'no'], true),
            'datetime', 'date' => is_scalar($value) && strtotime((string) $value) !== false,
            'json' => is_string($value) && json_decode($value) !== null && json_last_error() === JSON_ERROR_NONE,
            default => false,
        };
    }

    private static function stringAttr(SimpleXMLElement $xml, string $name): ?string
    {
        $value = trim((string) ($xml[$name] ?? ''));
        return $value === '' ? null : $value;
    }

    private static function intAttr(SimpleXMLElement $xml, string $name): ?int
    {
        $value = trim((string) ($xml[$name] ?? ''));
        return $value === '' ? null : (int) $value;
    }

    private static function floatAttr(SimpleXMLElement $xml, string $name): ?float
    {
        $value = trim((string) ($xml[$name] ?? ''));
        return $value === '' ? null : (float) $value;
    }

    private static function boolAttr(SimpleXMLElement $xml, string $name): bool
    {
        return in_array(strtolower(trim((string) ($xml[$name] ?? 'false'))), ['1', 'true', 'yes', 'on'], true);
    }
}

<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * One OPUS model field, independent from the concrete database engine.
 */
final class ModelField implements ModelFieldInterface
{
    private const TYPES = ['string', 'integer', 'decimal', 'float', 'boolean', 'date', 'datetime', 'binary', 'text', 'unknown'];

    private string $name;
    private string $type;
    private bool $nullable;
    private ?int $length;
    private ?int $precision;
    private ?int $scale;
    /** @var array<string,mixed> */
    private array $native;

    /** @param array<string,mixed> $native */
    public function __construct(string $name, string $type, bool $nullable = true, ?int $length = null, ?int $precision = null, ?int $scale = null, array $native = [])
    {
        $name = trim($name);
        $type = strtolower(trim($type));

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('OPUS_MODEL_FIELD_NAME_INVALID: ' . $name);
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('OPUS_MODEL_FIELD_TYPE_INVALID: ' . $type);
        }
        if ($length !== null && $length < 1) {
            throw new \InvalidArgumentException('OPUS_MODEL_FIELD_LENGTH_INVALID: ' . $name);
        }
        if ($precision !== null && $precision < 1) {
            throw new \InvalidArgumentException('OPUS_MODEL_FIELD_PRECISION_INVALID: ' . $name);
        }
        if ($scale !== null && $scale < 0) {
            throw new \InvalidArgumentException('OPUS_MODEL_FIELD_SCALE_INVALID: ' . $name);
        }

        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
        $this->length = $length;
        $this->precision = $precision;
        $this->scale = $scale;
        $this->native = $native;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function length(): ?int
    {
        return $this->length;
    }

    public function precision(): ?int
    {
        return $this->precision;
    }

    public function scale(): ?int
    {
        return $this->scale;
    }

    /** @return array<string,mixed> */
    public function native(): array
    {
        return $this->native;
    }

    /** @return list<string> */
    public function validateValue(mixed $value): array
    {
        if ($value === null) {
            return $this->nullable ? [] : ['OPUS_MODEL_FIELD_NULL_FORBIDDEN'];
        }

        $errors = [];
        if (!$this->matchesType($value)) {
            $errors[] = 'OPUS_MODEL_FIELD_TYPE_MISMATCH';
        }

        if ($this->length !== null && is_scalar($value) && strlen((string) $value) > $this->length) {
            $errors[] = 'OPUS_MODEL_FIELD_LENGTH_EXCEEDED';
        }

        return $errors;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'native' => $this->native,
        ];
    }

    private function matchesType(mixed $value): bool
    {
        if ($this->type === 'unknown') {
            return true;
        }
        if ($this->type === 'string' || $this->type === 'text' || $this->type === 'date' || $this->type === 'datetime' || $this->type === 'binary') {
            return is_scalar($value);
        }
        if ($this->type === 'integer') {
            return is_int($value) || (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1);
        }
        if ($this->type === 'decimal' || $this->type === 'float') {
            return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
        }
        if ($this->type === 'boolean') {
            return is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1';
        }

        return true;
    }
}

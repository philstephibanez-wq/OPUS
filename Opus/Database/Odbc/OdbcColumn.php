<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * ODBC column metadata normalized for OPUS Model construction.
 */
final class OdbcColumn implements OdbcColumnInterface
{
    private string $name;
    private string $nativeType;
    private ?int $numericType;
    private ?int $length;
    private ?int $scale;
    private bool $nullable;
    private int $ordinal;

    public function __construct(
        string $name,
        string $nativeType,
        ?int $numericType = null,
        ?int $length = null,
        ?int $scale = null,
        bool $nullable = true,
        int $ordinal = 0
    ) {
        $name = trim($name);

        if (
            preg_match(
                '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                $name
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_COLUMN_NAME_INVALID: ' . $name
            );
        }

        $this->name = $name;
        $this->nativeType = trim($nativeType) !== ''
            ? strtoupper(trim($nativeType))
            : 'UNKNOWN';
        $this->numericType = $numericType;
        $this->length = $length !== null && $length > 0
            ? $length
            : null;
        $this->scale = $scale !== null && $scale >= 0
            ? $scale
            : null;
        $this->nullable = $nullable;
        $this->ordinal = $ordinal > 0 ? $ordinal : 0;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nativeType(): string
    {
        return $this->nativeType;
    }

    public function numericType(): ?int
    {
        return $this->numericType;
    }

    public function length(): ?int
    {
        return $this->length;
    }

    public function scale(): ?int
    {
        return $this->scale;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'native_type' => $this->nativeType,
            'numeric_type' => $this->numericType,
            'length' => $this->length,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'ordinal' => $this->ordinal,
        ];
    }
}

<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer;

/**
 * Describes whether an Adminer/phpMyAdmin-like feature is implemented now,
 * read-only, guarded, driver-dependent or planned.
 */
final class OdbcExplorerCapability
{
    public const STATUS_CORE = 'core';
    public const STATUS_READONLY = 'readonly';
    public const STATUS_GUARDED = 'guarded';
    public const STATUS_DRIVER_DEPENDENT = 'driver_dependent';
    public const STATUS_PLANNED = 'planned';

    private string $feature;
    private string $status;
    private string $description;
    /** @var array<string,mixed> */
    private array $guards;

    /**
     * @param array<string,mixed> $guards
     */
    public function __construct(string $feature, string $status, string $description, array $guards = [])
    {
        if (!in_array($feature, OdbcExplorerFeature::all(), true)) {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_FEATURE_UNKNOWN: ' . $feature);
        }
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_CAPABILITY_STATUS_INVALID: ' . $status);
        }
        if (trim($description) === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_CAPABILITY_DESCRIPTION_EMPTY: ' . $feature);
        }

        $this->feature = $feature;
        $this->status = $status;
        $this->description = $description;
        $this->guards = $guards;
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_CORE,
            self::STATUS_READONLY,
            self::STATUS_GUARDED,
            self::STATUS_DRIVER_DEPENDENT,
            self::STATUS_PLANNED,
        ];
    }

    public function feature(): string
    {
        return $this->feature;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array<string,mixed>
     */
    public function guards(): array
    {
        return $this->guards;
    }

    public function isAvailableInContractCore(): bool
    {
        return in_array($this->status, [self::STATUS_CORE, self::STATUS_READONLY, self::STATUS_GUARDED], true);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'status' => $this->status,
            'description' => $this->description,
            'guards' => $this->guards,
        ];
    }
}

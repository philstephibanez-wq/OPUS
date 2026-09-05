<?php
declare(strict_types=1);

namespace Opus\Security\Runtime;

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;

/**
 * Durable fail-closed security quarantine for one OPUS application.
 *
 * This primitive deliberately exposes no unlock API. Administrative recovery
 * belongs to a separate management-plane component with its own ACL, fresh
 * authentication, audit and integrity checks.
 */
final class SecurityQuarantine implements SecurityQuarantineInterface
{
    public const CONTRACT = 'OPUS_SECURITY_QUARANTINE_V1';
    public const STATE = 'security_quarantine';
    public const RECOVERY_POLICY = 'manual_admin_required';

    private readonly File $file;
    private readonly StructuredFileLoader $loader;

    private function __construct(
        private readonly string $siteRoot,
        private readonly string $storePath
    ) {
        $this->file = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public static function forSiteRoot(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', trim($siteRoot)), '/');
        if ($siteRoot === ''
            || str_contains($siteRoot, "\0")
            || !is_dir($siteRoot)) {
            throw new \InvalidArgumentException(
                'OPUS_SECURITY_QUARANTINE_SITE_ROOT_INVALID'
            );
        }

        return new self(
            $siteRoot,
            $siteRoot . '/var/security/quarantine.json'
        );
    }

    public function quarantine(string $reasonCode): array
    {
        $reasonCode = strtoupper(trim($reasonCode));
        if (preg_match('/^[A-Z][A-Z0-9_.:-]{2,127}$/D', $reasonCode) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_SECURITY_QUARANTINE_REASON_INVALID'
            );
        }

        $directory = dirname($this->storePath);
        if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_DIRECTORY_CREATE_FAILED'
            );
        }

        $lockPath = $this->storePath . '.lock';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_LOCK_OPEN_FAILED'
            );
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException(
                    'OPUS_SECURITY_QUARANTINE_LOCK_FAILED'
                );
            }

            if ($this->file->exists($this->storePath)) {
                return $this->state();
            }

            $state = [
                'contract' => self::CONTRACT,
                'state' => self::STATE,
                'incident_id' => bin2hex(random_bytes(16)),
                'reason_code' => $reasonCode,
                'quarantined_at_utc' => gmdate('c'),
                'recovery_policy' => self::RECOVERY_POLICY,
            ];

            $this->file->writeAtomic(
                $this->storePath,
                Json::instance()->encode($state, true) . "\n"
            );

            if (DIRECTORY_SEPARATOR === '/') {
                @chmod($this->storePath, 0600);
            }

            return $this->state();
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function isQuarantined(): bool
    {
        return $this->file->exists($this->storePath);
    }

    public function state(): array
    {
        if (!$this->file->exists($this->storePath)) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_NOT_ACTIVE'
            );
        }

        try {
            $data = $this->loader->read($this->storePath);
        } catch (\Throwable $error) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_STORE_INVALID',
                0,
                $error
            );
        }

        if (($data['contract'] ?? null) !== self::CONTRACT
            || ($data['state'] ?? null) !== self::STATE
            || ($data['recovery_policy'] ?? null) !== self::RECOVERY_POLICY) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_STORE_INVALID'
            );
        }

        $incidentId = trim((string) ($data['incident_id'] ?? ''));
        $reasonCode = trim((string) ($data['reason_code'] ?? ''));
        $quarantinedAt = trim((string) ($data['quarantined_at_utc'] ?? ''));

        if (preg_match('/^[a-f0-9]{32}$/D', $incidentId) !== 1
            || preg_match('/^[A-Z][A-Z0-9_.:-]{2,127}$/D', $reasonCode) !== 1
            || $quarantinedAt === ''
            || strtotime($quarantinedAt) === false) {
            throw new \RuntimeException(
                'OPUS_SECURITY_QUARANTINE_STORE_INVALID'
            );
        }

        return [
            'contract' => self::CONTRACT,
            'state' => self::STATE,
            'incident_id' => $incidentId,
            'reason_code' => $reasonCode,
            'quarantined_at_utc' => $quarantinedAt,
            'recovery_policy' => self::RECOVERY_POLICY,
        ];
    }

    public function assertBusinessAllowed(): void
    {
        if (!$this->isQuarantined()) {
            return;
        }

        $state = $this->state();

        throw new \RuntimeException(
            'OPUS_SECURITY_QUARANTINE_ACTIVE:'
            . $state['incident_id']
        );
    }
}

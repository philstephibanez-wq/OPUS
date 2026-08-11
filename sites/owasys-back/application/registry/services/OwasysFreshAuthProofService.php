<?php
declare(strict_types=1);

use Opus\File\Json;

/**
 * Backend authority for short-lived fresh-auth proofs.
 *
 * Proofs contain no password or credential material. They are bound to the
 * authenticated actor, target application and exact mutation JSON.
 */
final class OwasysFreshAuthProofService
    implements OwasysFreshAuthProofServiceInterface
{
    private const CONTRACT = 'OWASYS_FRESH_AUTH_PROOF_V1';
    private const TTL_SECONDS = 120;
    private const SECRET_ENV = 'OPUS_OWASYS_FRESH_AUTH_PROOF_SECRET';

    private readonly Json $json;
    private readonly string $secret;

    public function __construct()
    {
        $this->json = Json::instance();
        $secret = getenv(self::SECRET_ENV);
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_SECRET_INVALID'
            );
        }
        $this->secret = $secret;
    }

    public function issue(
        array $actor,
        string $siteId,
        string $mutationJson,
        string $phase
    ): array {
        $claims = $this->claims(
            $actor,
            $siteId,
            $mutationJson,
            $phase
        );
        $now = time();
        $claims['contract'] = self::CONTRACT;
        $claims['issued_at'] = $now;
        $claims['expires_at'] = $now + self::TTL_SECONDS;
        $claims['nonce'] = bin2hex(random_bytes(16));

        $payload = $this->base64UrlEncode(
            $this->json->encode($claims, false)
        );
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return [
            'contract' => self::CONTRACT,
            'proof' => $payload . '.' . $signature,
            'expires_at' => gmdate(
                'Y-m-d\TH:i:s\Z',
                (int) $claims['expires_at']
            ),
        ];
    }

    public function assertValid(
        string $proof,
        array $actor,
        string $siteId,
        string $mutationJson,
        string $phase
    ): void {
        $parts = explode('.', trim($proof));
        if (count($parts) !== 2
            || preg_match('/^[A-Za-z0-9_-]{20,4096}$/D', $parts[0]) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $parts[1]) !== 1) {
            throw new RuntimeException('OWASYS_FRESH_AUTH_PROOF_INVALID');
        }

        [$payload, $providedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_SIGNATURE_INVALID'
            );
        }

        $claims = $this->json->parse(
            $this->base64UrlDecode($payload),
            'owasys:fresh-auth-proof'
        );
        if (($claims['contract'] ?? null) !== self::CONTRACT) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_CONTRACT_INVALID'
            );
        }

        $now = time();
        $issuedAt = (int) ($claims['issued_at'] ?? 0);
        $expiresAt = (int) ($claims['expires_at'] ?? 0);
        if ($issuedAt <= 0
            || $expiresAt <= $issuedAt
            || $issuedAt > $now + 15
            || $expiresAt < $now
            || ($expiresAt - $issuedAt) > self::TTL_SECONDS) {
            throw new RuntimeException('OWASYS_FRESH_AUTH_PROOF_EXPIRED');
        }

        $expected = $this->claims(
            $actor,
            $siteId,
            $mutationJson,
            $phase
        );
        foreach ($expected as $key => $value) {
            if (!is_string($claims[$key] ?? null)
                || !hash_equals($value, (string) $claims[$key])) {
                throw new RuntimeException(
                    'OWASYS_FRESH_AUTH_PROOF_BINDING_MISMATCH'
                );
            }
        }

        if (preg_match(
            '/^[a-f0-9]{32}$/D',
            (string) ($claims['nonce'] ?? '')
        ) !== 1) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_NONCE_INVALID'
            );
        }
    }

    /** @param array<string,mixed> $actor @return array<string,string> */
    private function claims(
        array $actor,
        string $siteId,
        string $mutationJson,
        string $phase
    ): array {
        $siteId = strtolower(trim($siteId));
        $phase = strtolower(trim($phase));
        $subject = trim((string) (
            $actor['subject'] ?? $actor['id'] ?? ''
        ));
        $provider = trim((string) ($actor['provider'] ?? ''));

        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || $subject === ''
            || $provider === ''
            || !in_array($phase, ['preview', 'commit'], true)
            || $mutationJson === ''
            || strlen($mutationJson) > 16384) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_BINDING_INVALID'
            );
        }

        return [
            'site_id' => $siteId,
            'subject' => $subject,
            'provider' => $provider,
            'operation' => 'security.mutation.' . $phase,
            'phase' => $phase,
            'mutation_hash' => hash('sha256', $mutationJson),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (!is_string($decoded)) {
            throw new RuntimeException(
                'OWASYS_FRESH_AUTH_PROOF_PAYLOAD_INVALID'
            );
        }
        return $decoded;
    }
}

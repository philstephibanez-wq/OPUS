<?php
declare(strict_types=1);

namespace Opus\Profiler;

/** Recursively redacts secrets and bounds values retained for developer diagnostics. */
final class ProfilerContextSanitizer implements ProfilerContextSanitizerInterface
{
    private const MAX_DEPTH = 8;
    private const MAX_ITEMS = 100;
    private const MAX_STRING_BYTES = 16384;
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_PARTS = [
        'authorization', 'cookie', 'password', 'passwd', 'secret', 'token',
        'signature', 'nonce', 'api_key', 'apikey', 'private_key', 'credential',
    ];

    public function sanitize(mixed $value): mixed
    {
        return $this->sanitizeValue($value, 0, null);
    }

    private function sanitizeValue(mixed $value, int $depth, string|int|null $key): mixed
    {
        if ($this->sensitiveKey($key)) {
            return self::REDACTED;
        }
        if ($depth >= self::MAX_DEPTH) {
            return '[MAX_DEPTH]';
        }
        if (is_string($value)) {
            if (strlen($value) <= self::MAX_STRING_BYTES) {
                return $value;
            }
            return substr($value, 0, self::MAX_STRING_BYTES)
                . sprintf('[TRUNCATED:%d_BYTES]', strlen($value) - self::MAX_STRING_BYTES);
        }
        if (is_array($value)) {
            $sanitized = [];
            $count = 0;
            foreach ($value as $itemKey => $itemValue) {
                if ($count >= self::MAX_ITEMS) {
                    $sanitized['_profiler_truncated_items'] = count($value) - self::MAX_ITEMS;
                    break;
                }
                $sanitized[$itemKey] = $this->sanitizeValue(
                    $itemValue,
                    $depth + 1,
                    is_string($itemKey) || is_int($itemKey) ? $itemKey : null
                );
                ++$count;
            }
            return $sanitized;
        }
        if (is_object($value)) {
            return ['object_class' => $value::class];
        }
        if (is_resource($value)) {
            return ['resource_type' => get_resource_type($value)];
        }
        return $value;
    }

    private function sensitiveKey(string|int|null $key): bool
    {
        if (!is_string($key)) {
            return false;
        }
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($key)));
        foreach (self::SENSITIVE_PARTS as $part) {
            if ($normalized === $part || str_contains($normalized, $part)) {
                return true;
            }
        }
        return false;
    }
}

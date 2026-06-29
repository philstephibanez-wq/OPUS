<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Conservative SQL guard for OPUS ODBC Explorer read-only console.
 */
final class OdbcReadOnlySqlGuard
{
    /** @var list<string> */
    private const FORBIDDEN_KEYWORDS = [
        'ALTER', 'CALL', 'CREATE', 'DELETE', 'DROP', 'EXEC', 'EXECUTE', 'GRANT',
        'IMPORT', 'INSERT', 'MERGE', 'REPLACE', 'REVOKE', 'TRUNCATE', 'UPDATE',
    ];

    public static function assertReadOnly(string $sql): string
    {
        $normalized = self::normalize($sql);
        if ($normalized === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_READONLY_SQL_EMPTY');
        }
        if (substr_count($normalized, ';') > 1 || (str_contains($normalized, ';') && !str_ends_with($normalized, ';'))) {
            throw new \InvalidArgumentException('OPUS_ODBC_READONLY_SQL_MULTIPLE_STATEMENTS_FORBIDDEN');
        }

        $withoutFinalSemicolon = rtrim($normalized, ';');
        if (!preg_match('/^(SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $withoutFinalSemicolon)) {
            throw new \InvalidArgumentException('OPUS_ODBC_READONLY_SQL_VERB_FORBIDDEN');
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $withoutFinalSemicolon)) {
                throw new \InvalidArgumentException('OPUS_ODBC_READONLY_SQL_KEYWORD_FORBIDDEN: ' . $keyword);
            }
        }

        return $withoutFinalSemicolon;
    }

    private static function normalize(string $sql): string
    {
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/--[^\r\n]*/', ' ', $sql) ?? $sql;
        $sql = preg_replace('/#[^\r\n]*/', ' ', $sql) ?? $sql;
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return trim($sql);
    }
}

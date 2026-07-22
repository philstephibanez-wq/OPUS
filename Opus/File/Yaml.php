<?php
declare(strict_types=1);

namespace Opus\File;

/**
 * Safe YAML configuration parser.
 *
 * Supported: mappings, sequences, nested indentation, quoted strings, booleans,
 * nulls, numbers and JSON-compatible inline arrays/maps. Executable tags,
 * aliases, anchors, merge keys and block scalars are rejected explicitly.
 */
final class Yaml implements YamlInterface
{
    public const CONTRACT = 'OPUS_YAML_PARSER_V1';
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function extensions(): array
    {
        return ['yaml', 'yml'];
    }

    public function parse(string $contents, string $source = ''): array
    {
        if (str_contains($contents, "\t")) {
            throw $this->error('TAB_INDENTATION_FORBIDDEN', $source);
        }
        $rows = [];
        foreach (preg_split('/\R/u', $contents) ?: [] as $number => $line) {
            $line = rtrim($line, " \r\n");
            $trimmed = ltrim($line, ' ');
            if ($trimmed === '' || str_starts_with($trimmed, '#') || $trimmed === '---' || $trimmed === '...') {
                continue;
            }
            $indent = strlen($line) - strlen($trimmed);
            $rows[] = ['indent' => $indent, 'text' => $this->stripComment($trimmed), 'line' => $number + 1];
        }
        if ($rows === []) {
            return [];
        }
        $index = 0;
        $result = $this->block($rows, $index, $rows[0]['indent'], $source);
        if ($index !== count($rows) || !is_array($result)) {
            throw $this->error('ROOT_ARRAY_REQUIRED', $source);
        }
        return $result;
    }

    /** @param list<array{indent:int,text:string,line:int}> $rows */
    private function block(array $rows, int &$index, int $indent, string $source): array
    {
        if (!isset($rows[$index]) || $rows[$index]['indent'] !== $indent) {
            throw $this->lineError('INDENTATION_INVALID', $source, $rows[$index]['line'] ?? 0);
        }
        $sequence = str_starts_with($rows[$index]['text'], '- ')
            || $rows[$index]['text'] === '-';
        $result = [];
        while (isset($rows[$index])) {
            $row = $rows[$index];
            if ($row['indent'] < $indent) {
                break;
            }
            if ($row['indent'] > $indent) {
                throw $this->lineError('UNEXPECTED_INDENTATION', $source, $row['line']);
            }
            $isSequence = str_starts_with($row['text'], '- ') || $row['text'] === '-';
            if ($isSequence !== $sequence) {
                throw $this->lineError('MAPPING_SEQUENCE_MIXED', $source, $row['line']);
            }
            if ($sequence) {
                $payload = trim(substr($row['text'], 1));
                $index++;
                if ($payload === '') {
                    $result[] = $this->nested($rows, $index, $indent, $source, $row['line']);
                    continue;
                }
                $pair = $this->mappingPair($payload);
                if ($pair !== null) {
                    [$key, $valueText] = $pair;
                    $item = [];
                    if ($valueText === '') {
                        $item[$key] = $this->nested($rows, $index, $indent, $source, $row['line']);
                    } else {
                        $item[$key] = $this->scalar($valueText, $source, $row['line']);
                    }
                    if (isset($rows[$index]) && $rows[$index]['indent'] > $indent) {
                        $childIndent = $rows[$index]['indent'];
                        $extra = $this->block($rows, $index, $childIndent, $source);
                        if (array_is_list($extra)) {
                            throw $this->lineError('SEQUENCE_MAP_EXTENSION_INVALID', $source, $row['line']);
                        }
                        $item = array_replace($item, $extra);
                    }
                    $result[] = $item;
                    continue;
                }
                $result[] = $this->scalar($payload, $source, $row['line']);
                continue;
            }

            $pair = $this->mappingPair($row['text']);
            if ($pair === null) {
                throw $this->lineError('MAPPING_ENTRY_INVALID', $source, $row['line']);
            }
            [$key, $valueText] = $pair;
            if (array_key_exists($key, $result)) {
                throw $this->lineError('DUPLICATE_KEY:' . $key, $source, $row['line']);
            }
            $index++;
            $result[$key] = $valueText === ''
                ? $this->nested($rows, $index, $indent, $source, $row['line'])
                : $this->scalar($valueText, $source, $row['line']);
        }
        return $result;
    }

    /** @param list<array{indent:int,text:string,line:int}> $rows */
    private function nested(array $rows, int &$index, int $parentIndent, string $source, int $line): array
    {
        if (!isset($rows[$index]) || $rows[$index]['indent'] <= $parentIndent) {
            throw $this->lineError('NESTED_BLOCK_REQUIRED', $source, $line);
        }
        return $this->block($rows, $index, $rows[$index]['indent'], $source);
    }

    /** @return array{0:string,1:string}|null */
    private function mappingPair(string $text): ?array
    {
        $quoted = null;
        $escaped = false;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\' && $quoted === '"') {
                $escaped = true;
                continue;
            }
            if (($char === '"' || $char === "'") && ($quoted === null || $quoted === $char)) {
                $quoted = $quoted === null ? $char : null;
                continue;
            }
            if ($char === ':' && $quoted === null) {
                $key = trim(substr($text, 0, $i));
                if ($key === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1) {
                    return null;
                }
                return [$key, trim(substr($text, $i + 1))];
            }
        }
        return null;
    }

    private function scalar(string $value, string $source, int $line): mixed
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^(?:[&*!]|<<:|[|>])/', $value) === 1) {
            throw $this->lineError('UNSAFE_OR_EMPTY_SCALAR', $source, $line);
        }
        if ($value[0] === '"') {
            try {
                return json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw $this->lineError('DOUBLE_QUOTED_STRING_INVALID', $source, $line, $error);
            }
        }
        if ($value[0] === "'") {
            if (!str_ends_with($value, "'")) {
                throw $this->lineError('SINGLE_QUOTED_STRING_INVALID', $source, $line);
            }
            return str_replace("''", "'", substr($value, 1, -1));
        }
        $lower = strtolower($value);
        if (in_array($lower, ['null', '~'], true)) return null;
        if (in_array($lower, ['true', 'yes', 'on'], true)) return true;
        if (in_array($lower, ['false', 'no', 'off'], true)) return false;
        if (preg_match('/^[+-]?[0-9]+$/', $value) === 1) return (int) $value;
        if (preg_match('/^[+-]?(?:[0-9]+\.[0-9]*|[0-9]*\.[0-9]+)(?:e[+-]?[0-9]+)?$/i', $value) === 1) return (float) $value;
        if (($value[0] === '[' && str_ends_with($value, ']')) || ($value[0] === '{' && str_ends_with($value, '}'))) {
            try {
                return json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw $this->lineError('INLINE_JSON_INVALID', $source, $line, $error);
            }
        }
        return $value;
    }

    private function stripComment(string $line): string
    {
        $quote = null;
        $escaped = false;
        for ($i = 0, $n = strlen($line); $i < $n; $i++) {
            $char = $line[$i];
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\' && $quote === '"') { $escaped = true; continue; }
            if (($char === '"' || $char === "'") && ($quote === null || $quote === $char)) {
                $quote = $quote === null ? $char : null;
                continue;
            }
            if ($char === '#' && $quote === null && ($i === 0 || ctype_space($line[$i - 1]))) {
                return rtrim(substr($line, 0, $i));
            }
        }
        return $line;
    }

    private function error(string $code, string $source): \RuntimeException
    {
        return new \RuntimeException('OPUS_YAML_' . $code . ':' . $source);
    }

    private function lineError(string $code, string $source, int $line, ?\Throwable $previous = null): \RuntimeException
    {
        return new \RuntimeException('OPUS_YAML_' . $code . ':' . $source . ':' . $line, 0, $previous);
    }
}

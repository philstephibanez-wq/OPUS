<?php
declare(strict_types=1);

namespace Opus\Template;

use DateTimeImmutable;
use Opus\Contract\ContractException;
use Opus\I18n\Gender;
use Opus\I18n\TranslationRuntimeInterface;
use Stringable;

final class ScoreTemplateRenderer implements TemplateRendererInterface,
    ScoreTemplateRendererInterface
{
    private string $templateRoot;

    public function __construct(
        string $templateRoot,
        private readonly ?TranslationRuntimeInterface $i18n = null
    ) {
        if (!is_dir($templateRoot)) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_ROOT_MISSING',
                $templateRoot
            );
        }

        $realRoot = realpath($templateRoot);

        if ($realRoot === false) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_ROOT_INVALID',
                $templateRoot
            );
        }

        $this->templateRoot = rtrim(
            str_replace('\\', '/', $realRoot),
            '/'
        );
    }

    public function render(string $template, array $data): string
    {
        $template = trim($template);

        if ($template === '') {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_EMPTY'
            );
        }

        return $this->renderTemplate($template, $data, []);
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function renderTemplate(
        string $template,
        array $data,
        array $stack
    ): string {
        if (in_array($template, $stack, true)) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_INCLUDE_CYCLE',
                implode(' > ', [...$stack, $template])
            );
        }

        $path = $this->resolveTemplatePath($template);
        $source = file_get_contents($path);

        if ($source === false) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_READ_FAILED',
                $template
            );
        }

        $tokens = $this->tokenize($source, $template);
        $index = 0;
        [$nodes, $terminator] = $this->parseNodes(
            $tokens,
            $index,
            [],
            $template
        );

        if ($terminator !== null || $index < count($tokens)) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_PARSE_INCOMPLETE',
                $template
            );
        }

        $stack[] = $template;

        return $this->renderNodes($nodes, $data, $stack);
    }

    /**
     * @return list<array{type:string,value:string,line:int,column:int}>
     */
    private function tokenize(string $source, string $template): array
    {
        $tokens = [];
        $offset = 0;
        $result = preg_match_all(
            '/\[\[\s*(.*?)\s*\]\]/s',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if ($result === false) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_DIRECTIVE_TOKENIZE_FAILED',
                $template
            );
        }

        foreach ($matches[0] as $position => $fullMatch) {
            $start = (int) $fullMatch[1];

            if ($start > $offset) {
                $tokens[] = $this->token(
                    'text',
                    substr($source, $offset, $start - $offset),
                    $source,
                    $offset
                );
            }

            $tokens[] = $this->token(
                'directive',
                trim((string) $matches[1][$position][0]),
                $source,
                $start
            );
            $offset = $start + strlen((string) $fullMatch[0]);
        }

        if ($offset < strlen($source)) {
            $tokens[] = $this->token(
                'text',
                substr($source, $offset),
                $source,
                $offset
            );
        }

        return $tokens;
    }

    /**
     * @return array{type:string,value:string,line:int,column:int}
     */
    private function token(
        string $type,
        string $value,
        string $source,
        int $offset
    ): array {
        $before = substr($source, 0, $offset);
        $line = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false
            ? $offset + 1
            : $offset - $lastNewline;

        return compact('type', 'value', 'line', 'column');
    }

    /**
     * @param list<array{type:string,value:string,line:int,column:int}> $tokens
     * @param list<string> $terminators
     * @return array{0:list<array<string,mixed>>,1:?string}
     */
    private function parseNodes(
        array $tokens,
        int &$index,
        array $terminators,
        string $template
    ): array {
        $nodes = [];

        while ($index < count($tokens)) {
            $token = $tokens[$index];
            $type = $token['type'];
            $value = trim($token['value']);

            if ($type === 'text') {
                $nodes[] = ['type' => 'text', 'value' => $token['value']];
                $index++;
                continue;
            }

            $normalized = strtolower($value);

            if (in_array($normalized, $terminators, true)) {
                $index++;
                return [$nodes, $normalized];
            }

            if (
                in_array(
                    $normalized,
                    ['else', 'endif', 'endforeach', 'endignore'],
                    true
                )
            ) {
                throw ContractException::because(
                    'OPUS_SCORE_TEMPLATE_UNEXPECTED_DIRECTIVE',
                    $value . ' in ' . $template . ':'
                        . $token['line'] . ':' . $token['column']
                );
            }

            if (
                $normalized === 'ignore'
                || str_starts_with($normalized, 'ignore:')
            ) {
                $index++;
                $this->skipIgnoredBlock(
                    $tokens,
                    $index,
                    $template,
                    $token['line']
                );
                continue;
            }

            if (
                preg_match(
                    '/^include\s*:\s*([A-Za-z0-9_.\/-]+)$/',
                    $value,
                    $matches
                ) === 1
            ) {
                $nodes[] = [
                    'type' => 'include',
                    'template' => $matches[1],
                ];
                $index++;
                continue;
            }

            if (
                preg_match('/^if\s*:\s*(.+)$/s', $value, $matches) === 1
            ) {
                $index++;
                [$thenNodes, $first] = $this->parseNodes(
                    $tokens,
                    $index,
                    ['else', 'endif'],
                    $template
                );
                $elseNodes = [];

                if ($first === 'else') {
                    [$elseNodes, $second] = $this->parseNodes(
                        $tokens,
                        $index,
                        ['endif'],
                        $template
                    );

                    if ($second !== 'endif') {
                        throw ContractException::because(
                            'OPUS_SCORE_TEMPLATE_IF_NOT_CLOSED',
                            $template . ':' . $token['line']
                        );
                    }
                } elseif ($first !== 'endif') {
                    throw ContractException::because(
                        'OPUS_SCORE_TEMPLATE_IF_NOT_CLOSED',
                        $template . ':' . $token['line']
                    );
                }

                $nodes[] = [
                    'type' => 'if',
                    'expression' => trim($matches[1]),
                    'then' => $thenNodes,
                    'else' => $elseNodes,
                ];
                continue;
            }

            if (
                preg_match(
                    '/^foreach\s*:\s*([A-Za-z0-9_.]+)\s+as\s+'
                    . '(?:(\$?[A-Za-z_][A-Za-z0-9_]*)\s*,\s*)?'
                    . '(\$?[A-Za-z_][A-Za-z0-9_]*)$/',
                    $value,
                    $matches
                ) === 1
            ) {
                $index++;
                [$children, $terminator] = $this->parseNodes(
                    $tokens,
                    $index,
                    ['endforeach'],
                    $template
                );

                if ($terminator !== 'endforeach') {
                    throw ContractException::because(
                        'OPUS_SCORE_TEMPLATE_FOREACH_NOT_CLOSED',
                        $template . ':' . $token['line']
                    );
                }

                $nodes[] = [
                    'type' => 'foreach',
                    'path' => $matches[1],
                    'key' => ($matches[2] ?? '') !== ''
                        ? ltrim($matches[2], '$')
                        : null,
                    'value' => ltrim($matches[3], '$'),
                    'children' => $children,
                ];
                continue;
            }

            if (
                preg_match(
                    '/^i18n\s*:\s*'
                    . '([a-z0-9]+(?:[._-][a-z0-9]+)*)'
                    . '(?:\s+(.*))?$/s',
                    $value,
                    $matches
                ) === 1
            ) {
                $nodes[] = [
                    'type' => 'i18n',
                    'key' => $matches[1],
                    'arguments' => $this->parseAssignments(
                        trim((string) ($matches[2] ?? '')),
                        $template,
                        $token['line']
                    ),
                ];
                $index++;
                continue;
            }

            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_UNKNOWN_DIRECTIVE',
                $value . ' in ' . $template . ':'
                    . $token['line'] . ':' . $token['column']
            );
        }

        return [$nodes, null];
    }

    /**
     * @return array<string,string>
     */
    private function parseAssignments(
        string $source,
        string $template,
        int $line
    ): array {
        if ($source === '') {
            return [];
        }

        $arguments = [];
        $length = strlen($source);
        $offset = 0;

        while ($offset < $length) {
            while ($offset < $length && ctype_space($source[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            if (
                preg_match(
                    '/\G([A-Za-z_][A-Za-z0-9_]*)=/A',
                    $source,
                    $nameMatch,
                    0,
                    $offset
                ) !== 1
            ) {
                throw ContractException::because(
                    'OPUS_SCORE_I18N_ARGUMENT_INVALID',
                    $template . ':' . $line . ':' . substr($source, $offset)
                );
            }

            $name = $nameMatch[1];
            $offset += strlen($nameMatch[0]);

            if ($offset >= $length) {
                throw ContractException::because(
                    'OPUS_SCORE_I18N_ARGUMENT_VALUE_MISSING',
                    $template . ':' . $line . ':' . $name
                );
            }

            $quote = $source[$offset];
            $value = '';

            if ($quote === '"' || $quote === "'") {
                $offset++;
                $escaped = false;

                while ($offset < $length) {
                    $char = $source[$offset++];

                    if ($escaped) {
                        $value .= $char;
                        $escaped = false;
                        continue;
                    }

                    if ($char === '\\') {
                        $escaped = true;
                        continue;
                    }

                    if ($char === $quote) {
                        break;
                    }

                    $value .= $char;
                }

                $operand = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );
            } else {
                $start = $offset;
                while (
                    $offset < $length
                    && !ctype_space($source[$offset])
                ) {
                    $offset++;
                }
                $operand = substr($source, $start, $offset - $start);
            }

            if ($operand === '') {
                throw ContractException::because(
                    'OPUS_SCORE_I18N_ARGUMENT_VALUE_MISSING',
                    $template . ':' . $line . ':' . $name
                );
            }

            $arguments[$name] = $operand;
        }

        return $arguments;
    }

    /**
     * @param list<array{type:string,value:string,line:int,column:int}> $tokens
     */
    private function skipIgnoredBlock(
        array $tokens,
        int &$index,
        string $template,
        int $startLine
    ): void {
        $depth = 1;

        while ($index < count($tokens)) {
            $token = $tokens[$index];

            if (($token['type'] ?? '') === 'directive') {
                $value = strtolower(
                    trim((string) ($token['value'] ?? ''))
                );

                if ($value === 'ignore' || str_starts_with($value, 'ignore:')) {
                    $depth++;
                } elseif ($value === 'endignore') {
                    $depth--;

                    if ($depth === 0) {
                        $index++;
                        return;
                    }
                }
            }

            $index++;
        }

        throw ContractException::because(
            'OPUS_SCORE_TEMPLATE_UNCLOSED_IGNORE',
            $template . ':' . $startLine
        );
    }

    /**
     * @param list<array<string,mixed>> $nodes
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function renderNodes(
        array $nodes,
        array $data,
        array $stack
    ): string {
        $output = '';

        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? '');

            if ($type === 'text') {
                $output .= $this->renderInterpolations(
                    (string) $node['value'],
                    $data
                );
                continue;
            }

            if ($type === 'include') {
                $output .= $this->renderTemplate(
                    (string) $node['template'],
                    $data,
                    $stack
                );
                continue;
            }

            if ($type === 'if') {
                $branch = $this->evaluateExpression(
                    (string) $node['expression'],
                    $data
                )
                    ? ($node['then'] ?? [])
                    : ($node['else'] ?? []);

                if (!is_array($branch)) {
                    throw ContractException::because(
                        'OPUS_SCORE_TEMPLATE_IF_BRANCH_INVALID'
                    );
                }

                $output .= $this->renderNodes($branch, $data, $stack);
                continue;
            }

            if ($type === 'foreach') {
                $items = $this->resolveDataPath(
                    $data,
                    (string) $node['path']
                );

                if (!is_iterable($items)) {
                    throw ContractException::because(
                        'OPUS_SCORE_TEMPLATE_FOREACH_NOT_ITERABLE',
                        (string) $node['path']
                    );
                }

                $entries = [];
                foreach ($items as $key => $value) {
                    $entries[] = [$key, $value];
                }

                $length = count($entries);

                foreach ($entries as $zeroIndex => [$key, $value]) {
                    $loopData = $data;
                    $keyName = $node['key'] ?? null;

                    if (is_string($keyName) && $keyName !== '') {
                        $loopData[$keyName] = $key;
                    }

                    $loopData[(string) $node['value']] = $value;
                    $loopData['loop'] = [
                        'index' => $zeroIndex + 1,
                        'index0' => $zeroIndex,
                        'first' => $zeroIndex === 0,
                        'last' => $zeroIndex === $length - 1,
                        'length' => $length,
                    ];

                    $children = $node['children'] ?? [];

                    if (!is_array($children)) {
                        throw ContractException::because(
                            'OPUS_SCORE_TEMPLATE_FOREACH_CHILDREN_INVALID'
                        );
                    }

                    $output .= $this->renderNodes(
                        $children,
                        $loopData,
                        $stack
                    );
                }
                continue;
            }

            if ($type === 'i18n') {
                if (!$this->i18n instanceof TranslationRuntimeInterface) {
                    throw ContractException::because(
                        'OPUS_SCORE_I18N_RUNTIME_MISSING',
                        (string) ($node['key'] ?? '')
                    );
                }

                $parameters = [];
                $count = null;
                $gender = null;

                foreach ((array) ($node['arguments'] ?? []) as $name => $operand) {
                    $value = $this->resolveExpressionOperand(
                        (string) $operand,
                        $data
                    );

                    if ($name === 'count') {
                        if (!is_int($value) && !is_float($value)) {
                            if (
                                is_string($value)
                                && is_numeric($value)
                            ) {
                                $value = str_contains($value, '.')
                                    ? (float) $value
                                    : (int) $value;
                            } else {
                                throw ContractException::because(
                                    'OPUS_SCORE_I18N_COUNT_INVALID',
                                    (string) ($node['key'] ?? '')
                                );
                            }
                        }
                        $count = $value;
                    } elseif ($name === 'gender') {
                        if (!is_string($value) && !$value instanceof Gender) {
                            throw ContractException::because(
                                'OPUS_SCORE_I18N_GENDER_INVALID',
                                (string) ($node['key'] ?? '')
                            );
                        }
                        $gender = $value;
                    }

                    $parameters[(string) $name] = $value;
                }

                $translated = $this->i18n->translate(
                    (string) $node['key'],
                    $parameters,
                    $count,
                    $gender
                );

                $output .= htmlspecialchars(
                    $translated,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
                continue;
            }

            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_UNKNOWN_NODE',
                $type
            );
        }

        return $output;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderInterpolations(
        string $source,
        array $data
    ): string {
        $source = preg_replace_callback(
            '/\{\{\{\s*(.*?)\s*\}\}\}/s',
            fn (array $matches): string => $this->stringValue(
                $this->resolveFilteredValue(
                    $data,
                    trim((string) $matches[1])
                ),
                trim((string) $matches[1])
            ),
            $source
        );

        if ($source === null) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_RAW_PARSE_FAILED'
            );
        }

        $source = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/s',
            fn (array $matches): string => htmlspecialchars(
                $this->stringValue(
                    $this->resolveFilteredValue(
                        $data,
                        trim((string) $matches[1])
                    ),
                    trim((string) $matches[1])
                ),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ),
            $source
        );

        if ($source === null) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_ESCAPED_PARSE_FAILED'
            );
        }

        return $source;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function evaluateExpression(
        string $expression,
        array $data
    ): bool {
        $expression = trim($expression);

        if ($expression === '') {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_EMPTY_CONDITION'
            );
        }

        if (preg_match('/^not\s+(.+)$/', $expression, $matches) === 1) {
            return !$this->truthy(
                $this->resolveExpressionOperand(
                    trim($matches[1]),
                    $data,
                    false
                )
            );
        }

        if (
            preg_match(
                '/^(.+?)\s+is\s+(not\s+)?defined$/',
                $expression,
                $matches
            ) === 1
        ) {
            $defined = $this->dataPathExists(
                $data,
                trim($matches[1])
            );

            return trim((string) ($matches[2] ?? '')) === 'not'
                ? !$defined
                : $defined;
        }

        if (
            preg_match(
                '/^(.+?)\s+is\s+(not\s+)?empty$/',
                $expression,
                $matches
            ) === 1
        ) {
            $empty = !$this->truthy(
                $this->resolveExpressionOperand(
                    trim($matches[1]),
                    $data,
                    false
                )
            );

            return trim((string) ($matches[2] ?? '')) === 'not'
                ? !$empty
                : $empty;
        }

        if (
            preg_match(
                '/^(.+?)\s*(==|!=|>=|<=|>|<)\s*(.+)$/',
                $expression,
                $matches
            ) === 1
        ) {
            return $this->compare(
                $this->resolveExpressionOperand(
                    trim($matches[1]),
                    $data
                ),
                $matches[2],
                $this->resolveExpressionOperand(
                    trim($matches[3]),
                    $data
                )
            );
        }

        return $this->truthy(
            $this->resolveExpressionOperand($expression, $data)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveFilteredValue(
        array $data,
        string $expression
    ): mixed {
        $parts = array_map('trim', explode('|', $expression));
        $path = array_shift($parts);

        if (!is_string($path) || $path === '') {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_EMPTY_VARIABLE'
            );
        }

        $value = $this->resolveDataPath($data, $path);

        foreach ($parts as $filter) {
            $value = $this->applyFilter(
                $value,
                $filter,
                $expression
            );
        }

        return $value;
    }

    private function applyFilter(
        mixed $value,
        string $filter,
        string $expression
    ): mixed {
        $filter = trim($filter);

        if ($filter === '') {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_EMPTY_FILTER',
                $expression
            );
        }

        $name = $filter;
        $argument = null;

        if (str_contains($filter, ':')) {
            [$name, $argument] = explode(':', $filter, 2);
            $name = trim($name);
            $argument = $this->unquote(trim($argument));
        }

        return match ($name) {
            'upper' => strtoupper(
                $this->stringValue($value, $expression)
            ),
            'lower' => strtolower(
                $this->stringValue($value, $expression)
            ),
            'trim' => trim(
                $this->stringValue($value, $expression)
            ),
            'default' => $this->truthy($value)
                ? $value
                : (string) ($argument ?? ''),
            'date' => $this->formatDateValue(
                $value,
                (string) ($argument ?? 'Y-m-d'),
                $expression
            ),
            'length' => $this->lengthValue($value, $expression),
            default => throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_UNKNOWN_FILTER',
                $name . ' in ' . $expression
            ),
        };
    }

    private function formatDateValue(
        mixed $value,
        string $format,
        string $expression
    ): string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable('@' . $value))->format($format);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return (new DateTimeImmutable($value))->format($format);
            } catch (\Throwable) {
                throw ContractException::because(
                    'OPUS_SCORE_TEMPLATE_DATE_INVALID',
                    $expression
                );
            }
        }

        throw ContractException::because(
            'OPUS_SCORE_TEMPLATE_DATE_INVALID',
            $expression
        );
    }

    private function lengthValue(
        mixed $value,
        string $expression
    ): int {
        if (is_string($value)) {
            return function_exists('mb_strlen')
                ? mb_strlen($value)
                : strlen($value);
        }

        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        throw ContractException::because(
            'OPUS_SCORE_TEMPLATE_LENGTH_INVALID',
            $expression
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveExpressionOperand(
        string $operand,
        array $data,
        bool $strict = true
    ): mixed {
        $operand = trim($operand);

        if (
            (str_starts_with($operand, '"') && str_ends_with($operand, '"'))
            || (
                str_starts_with($operand, "'")
                && str_ends_with($operand, "'")
            )
        ) {
            return $this->unquote($operand);
        }

        return match (strtolower($operand)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($operand)
                ? (str_contains($operand, '.')
                    ? (float) $operand
                    : (int) $operand)
                : $this->resolveDataPath($data, $operand, $strict),
        };
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveDataPath(
        array $data,
        string $path,
        bool $strict = true
    ): mixed {
        $path = trim($path);

        if (
            $path === ''
            || preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*$/',
                $path
            ) !== 1
        ) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_DATA_PATH_INVALID',
                $path
            );
        }

        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            if (
                is_object($value)
                && isset($value->{$segment})
            ) {
                $value = $value->{$segment};
                continue;
            }

            if (!$strict) {
                return null;
            }

            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_DATA_MISSING',
                $path
            );
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function dataPathExists(array $data, string $path): bool
    {
        try {
            $this->resolveDataPath($data, $path);
            return true;
        } catch (ContractException) {
            return false;
        }
    }

    private function compare(
        mixed $left,
        string $operator,
        mixed $right
    ): bool {
        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            default => false,
        };
    }

    private function truthy(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    private function stringValue(
        mixed $value,
        string $expression
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw ContractException::because(
            'OPUS_SCORE_TEMPLATE_VALUE_NOT_STRINGABLE',
            $expression
        );
    }

    private function unquote(string $value): string
    {
        $value = trim($value);

        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function resolveTemplatePath(string $template): string
    {
        $template = str_replace('\\', '/', trim($template));

        if (
            $template === ''
            || str_starts_with($template, '/')
            || str_contains($template, '..')
            || preg_match('/^[A-Za-z0-9_.\/-]+$/', $template) !== 1
            || !str_ends_with($template, '.score')
        ) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_PATH_INVALID',
                $template
            );
        }

        $candidate = realpath(
            $this->templateRoot . '/' . $template
        );

        if ($candidate === false || !is_file($candidate)) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_MISSING',
                $template
            );
        }

        $normalized = str_replace('\\', '/', $candidate);
        $prefix = $this->templateRoot . '/';

        if (!str_starts_with($normalized, $prefix)) {
            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_OUTSIDE_ROOT',
                $template
            );
        }

        return $candidate;
    }
}

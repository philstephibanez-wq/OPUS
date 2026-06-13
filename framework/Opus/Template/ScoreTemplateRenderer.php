<?php

declare(strict_types=1);

namespace Opus\Template;

use DateTimeImmutable;
use Opus\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Class ScoreTemplateRenderer belongs to the TEMPLATE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the TEMPLATE domain
 *     - renders validated view data without Twig or Symfony dependencies
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - score-template-foundation
 *   diagrams:
 *     - template-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC RENDERER
 *
 * Role:
 *   Render Opus view data with the native ScoreTemplate engine.
 *
 * Responsibility:
 *   Own a dependency-free template boundary for one application template root.
 *
 * Contract:
 *   ScoreTemplate is explicit and dependency-free. It supports escaped and raw
 *   interpolation, includes, if/else blocks, foreach blocks, loop metadata and
 *   whitelisted deterministic filters. It does not parse Twig and it does not
 *   silently fall back to another renderer.
 *
 * Syntax:
 *   {{ path.to.value }}              escaped interpolation
 *   {{{ path.to.html }}}             raw interpolation
 *   {{ name|upper }}                 whitelisted filter
 *   [[ include:partials/card.score ]] include another template
 *   [[ if: user.isLogged ]]          conditional block
 *   [[ else ]]                       conditional alternative
 *   [[ endif ]]                      conditional end
 *   [[ foreach: items as item ]]     list loop
 *   [[ foreach: items as key, item ]] map loop
 *   [[ endforeach ]]                 loop end
 *
 * Since:
 *   P116A
 */
final class ScoreTemplateRenderer implements TemplateRendererInterface
{
    private string $templateRoot;

    public function __construct(string $templateRoot)
    {
        if (!is_dir($templateRoot)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_ROOT_MISSING', $templateRoot);
        }

        $realRoot = realpath($templateRoot);
        if ($realRoot === false) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_ROOT_INVALID', $templateRoot);
        }

        $this->templateRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
    }

    public function render(string $template, array $data): string
    {
        $template = trim($template);
        if ($template === '') {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_EMPTY');
        }

        return $this->renderTemplate($template, $data, []);
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function renderTemplate(string $template, array $data, array $stack): string
    {
        if (in_array($template, $stack, true)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_INCLUDE_CYCLE', implode(' > ', [...$stack, $template]));
        }

        $path = $this->resolveTemplatePath($template);
        $source = file_get_contents($path);
        if ($source === false) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_READ_FAILED', $template);
        }

        $tokens = $this->tokenize($source, $template);
        $index = 0;
        [$nodes, $terminator] = $this->parseNodes($tokens, $index, [], $template);

        if ($terminator !== null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_UNEXPECTED_DIRECTIVE', $terminator . ' in ' . $template);
        }

        if ($index < count($tokens)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_PARSE_INCOMPLETE', $template);
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

        if (preg_match_all('/\[\[\s*(.*?)\s*\]\]/s', $source, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_DIRECTIVE_TOKENIZE_FAILED', $template);
        }

        foreach ($matches[0] as $position => $fullMatch) {
            $directiveStart = (int) $fullMatch[1];
            if ($directiveStart > $offset) {
                $text = substr($source, $offset, $directiveStart - $offset);
                $tokens[] = $this->token('text', $text, $source, $offset);
            }

            $body = trim((string) $matches[1][$position][0]);
            $tokens[] = $this->token('directive', $body, $source, $directiveStart);
            $offset = $directiveStart + strlen((string) $fullMatch[0]);
        }

        if ($offset < strlen($source)) {
            $tokens[] = $this->token('text', substr($source, $offset), $source, $offset);
        }

        return $tokens;
    }

    /**
     * @return array{type:string,value:string,line:int,column:int}
     */
    private function token(string $type, string $value, string $source, int $offset): array
    {
        $before = substr($source, 0, $offset);
        $line = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false ? $offset + 1 : $offset - $lastNewline;

        return [
            'type' => $type,
            'value' => $value,
            'line' => $line,
            'column' => $column,
        ];
    }

    /**
     * @param list<array{type:string,value:string,line:int,column:int}> $tokens
     * @param list<string> $terminators
     * @return array{0:list<array<string,mixed>>,1:?string}
     */
    private function parseNodes(array $tokens, int &$index, array $terminators, string $template): array
    {
        $nodes = [];

        while ($index < count($tokens)) {
            $token = $tokens[$index];
            $type = $token['type'];
            $value = trim($token['value']);

            if ($type === 'text') {
                $nodes[] = [
                    'type' => 'text',
                    'value' => $token['value'],
                ];
                $index++;
                continue;
            }

            $normalized = strtolower($value);
            if (in_array($normalized, $terminators, true)) {
                $index++;
                return [$nodes, $normalized];
            }

            if ($normalized === 'else' || $normalized === 'endif' || $normalized === 'endforeach') {
                throw ContractException::because(
                    'OPUS_SCORE_TEMPLATE_UNEXPECTED_DIRECTIVE',
                    $value . ' in ' . $template . ':' . $token['line'] . ':' . $token['column']
                );
            }

            if (preg_match('/^include\s*:\s*([A-Za-z0-9_\.\/-]+)$/', $value, $matches) === 1) {
                $nodes[] = [
                    'type' => 'include',
                    'template' => $matches[1],
                ];
                $index++;
                continue;
            }

            if (preg_match('/^if\s*:\s*(.+)$/s', $value, $matches) === 1) {
                $index++;
                [$thenNodes, $firstTerminator] = $this->parseNodes($tokens, $index, ['else', 'endif'], $template);
                $elseNodes = [];

                if ($firstTerminator === 'else') {
                    [$elseNodes, $secondTerminator] = $this->parseNodes($tokens, $index, ['endif'], $template);
                    if ($secondTerminator !== 'endif') {
                        throw ContractException::because('OPUS_SCORE_TEMPLATE_IF_NOT_CLOSED', $template . ':' . $token['line']);
                    }
                } elseif ($firstTerminator !== 'endif') {
                    throw ContractException::because('OPUS_SCORE_TEMPLATE_IF_NOT_CLOSED', $template . ':' . $token['line']);
                }

                $nodes[] = [
                    'type' => 'if',
                    'expression' => trim($matches[1]),
                    'then' => $thenNodes,
                    'else' => $elseNodes,
                ];
                continue;
            }

            if (preg_match('/^foreach\s*:\s*([A-Za-z0-9_\.]+)\s+as\s+(?:(\$?[A-Za-z_][A-Za-z0-9_]*)\s*,\s*)?(\$?[A-Za-z_][A-Za-z0-9_]*)$/', $value, $matches) === 1) {
                $index++;
                [$childNodes, $terminator] = $this->parseNodes($tokens, $index, ['endforeach'], $template);
                if ($terminator !== 'endforeach') {
                    throw ContractException::because('OPUS_SCORE_TEMPLATE_FOREACH_NOT_CLOSED', $template . ':' . $token['line']);
                }

                $nodes[] = [
                    'type' => 'foreach',
                    'path' => $matches[1],
                    'key' => isset($matches[2]) && $matches[2] !== '' ? ltrim($matches[2], '$') : null,
                    'value' => ltrim($matches[3], '$'),
                    'children' => $childNodes,
                ];
                continue;
            }

            throw ContractException::because(
                'OPUS_SCORE_TEMPLATE_UNKNOWN_DIRECTIVE',
                $value . ' in ' . $template . ':' . $token['line'] . ':' . $token['column']
            );
        }

        return [$nodes, null];
    }

    /**
     * @param list<array<string,mixed>> $nodes
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function renderNodes(array $nodes, array $data, array $stack): string
    {
        $output = '';

        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? '');

            if ($type === 'text') {
                $output .= $this->renderInterpolations((string) $node['value'], $data);
                continue;
            }

            if ($type === 'include') {
                $output .= $this->renderTemplate((string) $node['template'], $data, $stack);
                continue;
            }

            if ($type === 'if') {
                $branch = $this->evaluateExpression((string) $node['expression'], $data)
                    ? ($node['then'] ?? [])
                    : ($node['else'] ?? []);

                if (!is_array($branch)) {
                    throw ContractException::because('OPUS_SCORE_TEMPLATE_IF_BRANCH_INVALID');
                }

                $output .= $this->renderNodes($branch, $data, $stack);
                continue;
            }

            if ($type === 'foreach') {
                $items = $this->resolveDataPath($data, (string) $node['path']);
                if (!is_iterable($items)) {
                    throw ContractException::because('OPUS_SCORE_TEMPLATE_FOREACH_NOT_ITERABLE', (string) $node['path']);
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
                        throw ContractException::because('OPUS_SCORE_TEMPLATE_FOREACH_CHILDREN_INVALID');
                    }

                    $output .= $this->renderNodes($children, $loopData, $stack);
                }
                continue;
            }

            throw ContractException::because('OPUS_SCORE_TEMPLATE_UNKNOWN_NODE', $type);
        }

        return $output;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderInterpolations(string $source, array $data): string
    {
        $source = preg_replace_callback(
            '/\{\{\{\s*(.*?)\s*\}\}\}/s',
            function (array $matches) use ($data): string {
                $expression = trim((string) $matches[1]);
                return $this->stringValue($this->resolveFilteredValue($data, $expression), $expression);
            },
            $source
        );

        if ($source === null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_RAW_PARSE_FAILED');
        }

        $source = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/s',
            function (array $matches) use ($data): string {
                $expression = trim((string) $matches[1]);
                return htmlspecialchars(
                    $this->stringValue($this->resolveFilteredValue($data, $expression), $expression),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            },
            $source
        );

        if ($source === null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_ESCAPED_PARSE_FAILED');
        }

        return $source;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function evaluateExpression(string $expression, array $data): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_EMPTY_CONDITION');
        }

        if (preg_match('/^not\s+(.+)$/', $expression, $matches) === 1) {
            return !$this->truthy($this->resolveExpressionOperand(trim($matches[1]), $data));
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?defined$/', $expression, $matches) === 1) {
            $defined = $this->dataPathExists($data, trim($matches[1]));
            return trim((string) ($matches[2] ?? '')) === 'not' ? !$defined : $defined;
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?empty$/', $expression, $matches) === 1) {
            $value = $this->resolveExpressionOperand(trim($matches[1]), $data, false);
            $empty = !$this->truthy($value);
            return trim((string) ($matches[2] ?? '')) === 'not' ? !$empty : $empty;
        }

        if (preg_match('/^(.+?)\s*(==|!=|>=|<=|>|<)\s*(.+)$/', $expression, $matches) === 1) {
            $left = $this->resolveExpressionOperand(trim($matches[1]), $data);
            $operator = $matches[2];
            $right = $this->resolveExpressionOperand(trim($matches[3]), $data);

            return $this->compare($left, $operator, $right);
        }

        return $this->truthy($this->resolveExpressionOperand($expression, $data));
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveFilteredValue(array $data, string $expression): mixed
    {
        $parts = array_map('trim', explode('|', $expression));
        $path = array_shift($parts);

        if (!is_string($path) || $path === '') {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_EMPTY_VARIABLE');
        }

        $value = $this->resolveDataPath($data, $path);

        foreach ($parts as $filter) {
            if ($filter === '') {
                throw ContractException::because('OPUS_SCORE_TEMPLATE_EMPTY_FILTER', $expression);
            }

            $value = $this->applyFilter($value, $filter, $expression);
        }

        return $value;
    }

    private function applyFilter(mixed $value, string $filter, string $expression): mixed
    {
        $name = $filter;
        $argument = null;

        if (str_contains($filter, ':')) {
            [$name, $argument] = explode(':', $filter, 2);
            $name = trim($name);
            $argument = $this->unquote(trim($argument));
        }

        return match ($name) {
            'upper' => strtoupper($this->stringValue($value, $expression)),
            'lower' => strtolower($this->stringValue($value, $expression)),
            'trim' => trim($this->stringValue($value, $expression)),
            'default' => $this->truthy($value) ? $value : (string) ($argument ?? ''),
            'date' => $this->formatDateValue($value, (string) ($argument ?? 'Y-m-d'), $expression),
            'length' => $this->lengthValue($value, $expression),
            default => throw ContractException::because('OPUS_SCORE_TEMPLATE_UNKNOWN_FILTER', $name . ' in ' . $expression),
        };
    }

    private function formatDateValue(mixed $value, string $format, string $expression): string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format($format);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_int($value)) {
            return (new DateTimeImmutable('@' . $value))->format($format);
        }

        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return (new DateTimeImmutable('@' . $timestamp))->format($format);
            }
        }

        throw ContractException::because('OPUS_SCORE_TEMPLATE_DATE_FILTER_INVALID_VALUE', $expression);
    }

    private function lengthValue(mixed $value, string $expression): int
    {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        if (is_string($value)) {
            return strlen($value);
        }

        throw ContractException::because('OPUS_SCORE_TEMPLATE_LENGTH_FILTER_INVALID_VALUE', $expression);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveExpressionOperand(string $operand, array $data, bool $required = true): mixed
    {
        $operand = trim($operand);

        if ($operand === 'true') {
            return true;
        }

        if ($operand === 'false') {
            return false;
        }

        if ($operand === 'null') {
            return null;
        }

        if (is_numeric($operand)) {
            return str_contains($operand, '.') ? (float) $operand : (int) $operand;
        }

        if ((str_starts_with($operand, '"') && str_ends_with($operand, '"'))
            || (str_starts_with($operand, "'") && str_ends_with($operand, "'"))
        ) {
            return stripcslashes(substr($operand, 1, -1));
        }

        if ($required || $this->dataPathExists($data, $operand)) {
            return $this->resolveDataPath($data, $operand);
        }

        return null;
    }

    private function compare(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => throw ContractException::because('OPUS_SCORE_TEMPLATE_UNKNOWN_OPERATOR', $operator),
        };
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if ($value === 0 || $value === 0.0 || $value === '0' || $value === '') {
            return false;
        }

        if (is_array($value) && $value === []) {
            return false;
        }

        return true;
    }

    private function resolveTemplatePath(string $template): string
    {
        if ($template !== basename($template) && str_contains($template, '..')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_PATH_TRAVERSAL', $template);
        }

        if (!str_ends_with($template, '.score')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_EXTENSION_INVALID', $template);
        }

        $candidate = $this->templateRoot . '/' . ltrim($template, '/');
        $realCandidate = realpath($candidate);

        if ($realCandidate === false || !is_file($realCandidate)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_NOT_FOUND', $template);
        }

        $normalized = str_replace('\\', '/', $realCandidate);
        if (!str_starts_with($normalized, $this->templateRoot . '/')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_OUTSIDE_ROOT', $template);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveDataPath(array $data, string $path): mixed
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $path)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_DATA_PATH_INVALID', $path);
        }

        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                throw ContractException::because('OPUS_SCORE_TEMPLATE_DATA_MISSING', $path);
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function dataPathExists(array $data, string $path): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $path)) {
            return false;
        }

        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }

    private function stringValue(mixed $value, string $path): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw ContractException::because('OPUS_SCORE_TEMPLATE_DATA_NOT_SCALAR', $path);
    }

    private function unquote(string $value): string
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }
}

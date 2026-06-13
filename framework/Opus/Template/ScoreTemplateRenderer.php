<?php

declare(strict_types=1);

namespace Opus\Template;

use DateTimeImmutable;
use Opus\Contract\ContractException;
use Stringable;
use Traversable;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Class ScoreTemplateRenderer belongs to the TEMPLATE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the TEMPLATE domain
 *     - renders validated view data without Twig, Smarty, Symfony or x64 dependencies
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - score-template-final-contract
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
 *   Own the dependency-free template boundary for one application template root.
 *
 * Contract:
 *   ScoreTemplate is the final Opus templating target. Templates represent only:
 *   they do not execute PHP, call services, query databases, route requests or
 *   silently fall back to Twig, Smarty, x64 or any legacy adapter.
 *
 * Syntax:
 *   {{ path.to.value }}                  escaped interpolation
 *   {{{ path.to.html }}}                 explicit raw interpolation
 *   {{ title|upper }}                    whitelisted filter
 *   {{ value|default:"—" }}             whitelisted filter argument
 *   [[ include:partials/header.score ]]  controlled include
 *   [[ if: user.enabled ]]...[[ endif ]] simple condition
 *   [[ foreach: items as item ]]...[[ endforeach ]] simple loop
 *   [[ foreach: items as key, item ]]...[[ endforeach ]] key/value loop
 *
 * Since:
 *   P116A
 *
 * Extended:
 *   P116B makes ScoreTemplate the native final contract and removes legacy
 *   adapter assumptions from the template recipe.
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
            throw $this->error('OPUS_SCORE_TEMPLATE_INCLUDE_CYCLE', $template, implode(' > ', [...$stack, $template]), $stack);
        }

        $path = $this->resolveTemplatePath($template);
        $source = file_get_contents($path);
        if ($source === false) {
            throw $this->error('OPUS_SCORE_TEMPLATE_READ_FAILED', $template, '', $stack);
        }

        if (str_contains($source, '<?')) {
            throw $this->error('OPUS_SCORE_TEMPLATE_PHP_FORBIDDEN', $template, 'PHP tags are forbidden in .score templates.', $stack);
        }

        $stack[] = $template;

        return $this->renderSource($source, $data, $template, $stack);
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function renderSource(string $source, array $data, string $template, array $stack): string
    {
        $source = $this->processIf($source, $data, $template, $stack);
        $source = $this->processForeach($source, $data, $template, $stack);
        $source = $this->processIncludes($source, $data, $template, $stack);
        $source = $this->processVariables($source, $data, $template, $stack);
        $this->assertNoUnknownScoreDirective($source, $template, $stack);
        $this->assertNoUnknownVariableSyntax($source, $template, $stack);

        return $source;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function processIncludes(string $source, array $data, string $template, array $stack): string
    {
        $rendered = preg_replace_callback(
            '/\[\[\s*include\s*:\s*([A-Za-z0-9_\.\/-]+\.score)\s*\]\]/',
            function (array $matches) use ($data, $stack): string {
                return $this->renderTemplate($matches[1], $data, $stack);
            },
            $source
        );

        if ($rendered === null) {
            throw $this->error('OPUS_SCORE_TEMPLATE_INCLUDE_PARSE_FAILED', $template, '', $stack);
        }

        return $rendered;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function processForeach(string $source, array $data, string $template, array $stack): string
    {
        $pattern = '/\[\[\s*foreach\s*:\s*([A-Za-z0-9_\.]+)\s+as\s+(?:(\$?[A-Za-z_][A-Za-z0-9_]*)\s*,\s*)?(\$?[A-Za-z_][A-Za-z0-9_]*)\s*\]\](.*?)\[\[\s*endforeach\s*\]\]/s';

        $rendered = preg_replace_callback(
            $pattern,
            function (array $matches) use ($data, $template, $stack): string {
                $collectionPath = $matches[1];
                $keyName = isset($matches[2]) && $matches[2] !== '' ? ltrim($matches[2], '$') : null;
                $itemName = ltrim($matches[3], '$');
                $body = $matches[4];
                $collection = $this->resolveDataPath($data, $collectionPath, $template, $stack);

                if ($collection instanceof Traversable) {
                    $collection = iterator_to_array($collection);
                }

                if (!is_array($collection)) {
                    throw $this->error('OPUS_SCORE_TEMPLATE_FOREACH_NOT_ITERABLE', $template, $collectionPath, $stack);
                }

                $count = count($collection);
                $index = 0;
                $html = '';

                foreach ($collection as $key => $value) {
                    $index++;
                    $childData = $data;
                    if ($keyName !== null) {
                        $childData[$keyName] = $key;
                    }
                    $childData[$itemName] = $value;
                    $childData['loop'] = [
                        'index' => $index,
                        'index0' => $index - 1,
                        'first' => $index === 1,
                        'last' => $index === $count,
                        'length' => $count,
                    ];

                    $html .= $this->renderSource($body, $childData, $template, $stack);
                }

                return $html;
            },
            $source
        );

        if ($rendered === null) {
            throw $this->error('OPUS_SCORE_TEMPLATE_FOREACH_PARSE_FAILED', $template, '', $stack);
        }

        return $rendered;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function processIf(string $source, array $data, string $template, array $stack): string
    {
        $pattern = '/\[\[\s*if\s*:\s*(.*?)\s*\]\](.*?)(?:\[\[\s*else\s*\]\](.*?))?\[\[\s*endif\s*\]\]/s';

        $rendered = preg_replace_callback(
            $pattern,
            function (array $matches) use ($data, $template, $stack): string {
                $condition = trim($matches[1]);
                $ifBody = $matches[2];
                $elseBody = $matches[3] ?? '';
                $selected = $this->evaluateCondition($condition, $data, $template, $stack) ? $ifBody : $elseBody;

                return $this->renderSource($selected, $data, $template, $stack);
            },
            $source
        );

        if ($rendered === null) {
            throw $this->error('OPUS_SCORE_TEMPLATE_IF_PARSE_FAILED', $template, '', $stack);
        }

        return $rendered;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function processVariables(string $source, array $data, string $template, array $stack): string
    {
        $source = preg_replace_callback(
            '/\{\{\{\s*(.*?)\s*\}\}\}/',
            function (array $matches) use ($data, $template, $stack): string {
                return $this->stringValue($this->evaluateExpression($matches[1], $data, $template, $stack), $matches[1], $template, $stack);
            },
            $source
        );

        if ($source === null) {
            throw $this->error('OPUS_SCORE_TEMPLATE_RAW_PARSE_FAILED', $template, '', $stack);
        }

        $source = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/',
            function (array $matches) use ($data, $template, $stack): string {
                return htmlspecialchars(
                    $this->stringValue($this->evaluateExpression($matches[1], $data, $template, $stack), $matches[1], $template, $stack),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            },
            $source
        );

        if ($source === null) {
            throw $this->error('OPUS_SCORE_TEMPLATE_ESCAPED_PARSE_FAILED', $template, '', $stack);
        }

        return $source;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function evaluateExpression(string $expression, array $data, string $template, array $stack): mixed
    {
        $parts = array_map('trim', explode('|', trim($expression)));
        $path = array_shift($parts) ?? '';
        if (!preg_match('/^[A-Za-z0-9_\.]+$/', $path)) {
            throw $this->error('OPUS_SCORE_TEMPLATE_EXPRESSION_INVALID', $template, $expression, $stack);
        }

        $allowMissing = $this->hasDefaultFilter($parts);
        $value = $this->resolveDataPath($data, $path, $template, $stack, $allowMissing);

        foreach ($parts as $filterExpression) {
            $value = $this->applyFilter($value, $filterExpression, $template, $stack);
        }

        return $value;
    }

    /** @param list<string> $filters */
    private function hasDefaultFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (preg_match('/^default\s*:/', $filter) === 1 || $filter === 'default') {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $stack */
    private function applyFilter(mixed $value, string $filterExpression, string $template, array $stack): mixed
    {
        $filterExpression = trim($filterExpression);
        if ($filterExpression === '') {
            throw $this->error('OPUS_SCORE_TEMPLATE_FILTER_EMPTY', $template, '', $stack);
        }

        [$name, $argument] = array_pad(explode(':', $filterExpression, 2), 2, null);
        $name = trim($name);
        $argument = $argument === null ? null : $this->parseFilterArgument(trim($argument));

        return match ($name) {
            'upper' => $this->toUpper($this->stringValue($value, $filterExpression, $template, $stack)),
            'lower' => $this->toLower($this->stringValue($value, $filterExpression, $template, $stack)),
            'trim' => trim($this->stringValue($value, $filterExpression, $template, $stack)),
            'default' => ($value === null || $value === '') ? ($argument ?? '') : $value,
            'date' => $this->formatDate($value, $argument ?? 'Y-m-d', $template, $stack),
            default => throw $this->error('OPUS_SCORE_TEMPLATE_FILTER_UNKNOWN', $template, $name, $stack),
        };
    }

    private function parseFilterArgument(string $argument): string
    {
        if ((str_starts_with($argument, '"') && str_ends_with($argument, '"')) || (str_starts_with($argument, "'") && str_ends_with($argument, "'"))) {
            return substr($argument, 1, -1);
        }

        return $argument;
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function evaluateCondition(string $expression, array $data, string $template, array $stack): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw $this->error('OPUS_SCORE_TEMPLATE_CONDITION_EMPTY', $template, '', $stack);
        }

        if (str_starts_with($expression, 'not ')) {
            return !$this->evaluateCondition(substr($expression, 4), $data, $template, $stack);
        }

        if (preg_match('/^(.+?)\s*(==|!=|>=|<=|>|<)\s*(.+)$/', $expression, $match) === 1) {
            $left = $this->resolveConditionOperand(trim($match[1]), $data, $template, $stack);
            $right = $this->resolveConditionOperand(trim($match[3]), $data, $template, $stack);

            return match ($match[2]) {
                '==' => $left == $right,
                '!=' => $left != $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '>' => $left > $right,
                '<' => $left < $right,
                default => false,
            };
        }

        return $this->truthy($this->resolveConditionOperand($expression, $data, $template, $stack));
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $stack
     */
    private function resolveConditionOperand(string $operand, array $data, string $template, array $stack): mixed
    {
        if ((str_starts_with($operand, '"') && str_ends_with($operand, '"')) || (str_starts_with($operand, "'") && str_ends_with($operand, "'"))) {
            return substr($operand, 1, -1);
        }

        if (is_numeric($operand)) {
            return str_contains($operand, '.') ? (float)$operand : (int)$operand;
        }

        return match ($operand) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $this->resolveDataPath($data, $operand, $template, $stack),
        };
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === 0 || $value === 0.0 || $value === '' || $value === []) {
            return false;
        }

        return true;
    }

    private function resolveTemplatePath(string $template): string
    {
        if (!preg_match('/^[A-Za-z0-9_\.\/-]+\.score$/', $template)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_PATH_INVALID', $template);
        }

        if (str_starts_with($template, '/') || str_contains($template, '\\') || str_contains($template, '..')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_PATH_FORBIDDEN', $template);
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
     * @param list<string> $stack
     */
    private function resolveDataPath(array $data, string $path, string $template, array $stack, bool $allowMissing = false): mixed
    {
        if (!preg_match('/^[A-Za-z0-9_\.]+$/', $path)) {
            throw $this->error('OPUS_SCORE_TEMPLATE_DATA_PATH_INVALID', $template, $path, $stack);
        }

        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
                continue;
            }

            if (is_object($cursor)) {
                if (isset($cursor->{$segment}) || property_exists($cursor, $segment)) {
                    $cursor = $cursor->{$segment};
                    continue;
                }

                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $segment)));
                $isser = 'is' . str_replace(' ', '', ucwords(str_replace('_', ' ', $segment)));
                if (method_exists($cursor, $getter)) {
                    $cursor = $cursor->{$getter}();
                    continue;
                }
                if (method_exists($cursor, $isser)) {
                    $cursor = $cursor->{$isser}();
                    continue;
                }
            }

            if ($allowMissing) {
                return null;
            }

            throw $this->error('OPUS_SCORE_TEMPLATE_DATA_MISSING', $template, $path, $stack);
        }

        return $cursor;
    }

    /** @param list<string> $stack */
    private function stringValue(mixed $value, string $expression, string $template, array $stack): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if ($value instanceof Stringable) {
            return (string)$value;
        }

        throw $this->error('OPUS_SCORE_TEMPLATE_DATA_NOT_SCALAR', $template, $expression, $stack);
    }

    /** @param list<string> $stack */
    private function formatDate(mixed $value, string $format, string $template, array $stack): string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format($format);
        }

        if (is_int($value)) {
            return date($format, $value);
        }

        $time = strtotime($this->stringValue($value, 'date', $template, $stack));
        if ($time === false) {
            throw $this->error('OPUS_SCORE_TEMPLATE_DATE_INVALID', $template, (string)$value, $stack);
        }

        return date($format, $time);
    }

    private function toUpper(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function toLower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /** @param list<string> $stack */
    private function assertNoUnknownScoreDirective(string $source, string $template, array $stack): void
    {
        if (preg_match('/\[\[(.*?)\]\]/s', $source, $match) === 1) {
            throw $this->error('OPUS_SCORE_TEMPLATE_DIRECTIVE_UNKNOWN', $template, trim($match[1]), $stack);
        }
    }

    /** @param list<string> $stack */
    private function assertNoUnknownVariableSyntax(string $source, string $template, array $stack): void
    {
        if (preg_match('/\{\{(.*?)\}\}/s', $source, $match) === 1) {
            throw $this->error('OPUS_SCORE_TEMPLATE_VARIABLE_UNKNOWN', $template, trim($match[1]), $stack);
        }
    }

    /** @param list<string> $stack */
    private function error(string $code, string $template, string $detail = '', array $stack = []): ContractException
    {
        $parts = ['template=' . $template];
        if ($stack !== []) {
            $parts[] = 'stack=' . implode(' > ', $stack);
        }
        if ($detail !== '') {
            $parts[] = 'detail=' . $detail;
        }

        return ContractException::because($code, implode(' | ', $parts));
    }
}

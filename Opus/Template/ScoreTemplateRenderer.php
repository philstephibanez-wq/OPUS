<?php
declare(strict_types=1);

namespace Opus\Template;

use Opus\Contract\ContractException;
use Traversable;

final class ScoreTemplateRenderer implements TemplateRendererInterface
{
    private string $root;

    public function __construct(string $templateRoot)
    {
        $real = realpath($templateRoot);
        if ($real === false || !is_dir($real)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_ROOT_INVALID', $templateRoot);
        }
        $this->root = rtrim(str_replace('\\', '/', $real), '/');
    }

    public function render(string $template, array $data): string
    {
        return $this->renderTemplate($template, $data, []);
    }

    private function renderTemplate(string $template, array $data, array $stack): string
    {
        if (!preg_match('#^[A-Za-z0-9_./-]+\.score$#', $template) || str_contains($template, '..')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_NAME_INVALID', $template);
        }
        if (in_array($template, $stack, true)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_INCLUDE_CYCLE', implode(' > ', [...$stack, $template]));
        }
        $file = realpath($this->root . '/' . $template);
        if ($file === false || !str_starts_with(str_replace('\\', '/', $file), $this->root . '/')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_MISSING', $template);
        }
        $source = file_get_contents($file);
        if (!is_string($source) || str_contains($source, '<?')) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_SOURCE_INVALID', $template);
        }
        return $this->renderSource($source, $data, $template, [...$stack, $template]);
    }

    private function renderSource(string $source, array $data, string $template, array $stack): string
    {
        $source = preg_replace_callback(
            '/\[\[\s*foreach\s*:\s*([A-Za-z0-9_.]+)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)\s*\]\](.*?)\[\[\s*endforeach\s*\]\]/s',
            function (array $m) use ($data, $template, $stack): string {
                $items = $this->value($data, $m[1]);
                if ($items instanceof Traversable) {
                    $items = iterator_to_array($items);
                }
                if (!is_array($items)) {
                    throw ContractException::because('OPUS_SCORE_TEMPLATE_FOREACH_NOT_ITERABLE', $m[1]);
                }
                $html = '';
                foreach ($items as $item) {
                    $child = $data;
                    $child[$m[2]] = $item;
                    $html .= $this->renderSource($m[3], $child, $template, $stack);
                }
                return $html;
            },
            $source
        );
        if ($source === null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_FOREACH_PARSE_FAILED', $template);
        }

        $source = preg_replace_callback(
            '/\[\[\s*if\s*:\s*([A-Za-z0-9_.]+)\s*\]\](.*?)(?:\[\[\s*else\s*\]\](.*?))?\[\[\s*endif\s*\]\]/s',
            fn(array $m): string => $this->truthy($this->value($data, $m[1], true))
                ? $this->renderSource($m[2], $data, $template, $stack)
                : $this->renderSource($m[3] ?? '', $data, $template, $stack),
            $source
        );
        if ($source === null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_IF_PARSE_FAILED', $template);
        }

        $source = preg_replace_callback(
            '/\[\[\s*include\s*:\s*([A-Za-z0-9_./-]+\.score)\s*\]\]/',
            fn(array $m): string => $this->renderTemplate($m[1], $data, $stack),
            $source
        );
        if ($source === null) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_INCLUDE_PARSE_FAILED', $template);
        }

        $source = preg_replace_callback('/\{\{\{\s*([A-Za-z0-9_.]+)\s*\}\}\}/', fn(array $m): string => $this->string($this->value($data, $m[1]), $m[1]), $source);
        $source = preg_replace_callback('/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/', fn(array $m): string => htmlspecialchars($this->string($this->value($data, $m[1]), $m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $source);
        if ($source === null || preg_match('/\[\[|\{\{/', $source)) {
            throw ContractException::because('OPUS_SCORE_TEMPLATE_UNRESOLVED_DIRECTIVE', $template);
        }
        return $source;
    }

    private function value(array $data, string $path, bool $allowMissing = false): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                if ($allowMissing) {
                    return null;
                }
                throw ContractException::because('OPUS_SCORE_TEMPLATE_DATA_MISSING', $path);
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private function string(mixed $value, string $path): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if ($value === null) {
            return '';
        }
        throw ContractException::because('OPUS_SCORE_TEMPLATE_VALUE_NOT_SCALAR', $path);
    }

    private function truthy(mixed $value): bool
    {
        return !($value === null || $value === false || $value === '' || $value === 0 || $value === []);
    }
}

<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Prepare readable API call examples for RefBook symbol pages.
 *
 * Responsibility:
 *   Transform manifest method metadata into display-ready examples.
 *   The service does not execute code, does not inspect runtime classes, and does
 *   not invent business behavior beyond method invocation shape.
 *
 * Contract:
 *   Data preparation only. No HTTP routing, no HTML rendering, no fallback source.
 *   Missing or invalid method metadata yields an empty examples list.
 */
final class ReferenceApiCallService
{
    /**
     * @param array<string,mixed> $symbol
     * @return list<array{method:string,signature:string,code:string,note:string}>
     */
    public function forSymbol(array $symbol): array
    {
        $fqcn = trim((string) ($symbol['symbol'] ?? $symbol['name'] ?? ''));
        if ($fqcn === '') {
            return [];
        }

        $methods = $symbol['methods'] ?? [];
        if (!is_array($methods) || $methods === []) {
            return [];
        }

        $kind = strtolower(trim((string) ($symbol['kind'] ?? $symbol['type'] ?? 'class')));
        $safeClass = '\\' . ltrim($fqcn, '\\');
        $instanceVariable = $this->instanceVariable($fqcn, $kind);
        $calls = [];

        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }

            $name = trim((string) ($method['name'] ?? ''));
            if ($name === '' || str_starts_with($name, '__')) {
                continue;
            }

            $signature = trim((string) ($method['signature'] ?? $name . '()'));
            $arguments = $this->argumentsFromSignature($signature);
            $isStatic = (bool) ($method['static'] ?? false) || stripos($signature, ' static function ') !== false;

            if ($isStatic) {
                $code = $safeClass . '::' . $name . '(' . $arguments . ');';
                $note = 'Static call generated from manifest metadata.';
            } elseif ($kind === 'interface') {
                $code = '$implementation->' . $name . '(' . $arguments . ');';
                $note = 'Interface call shown through an implementation instance.';
            } else {
                $code = $instanceVariable . ' = new ' . $safeClass . "(...);\n" .
                    $instanceVariable . '->' . $name . '(' . $arguments . ');';
                $note = 'Instance call shape generated from manifest metadata.';
            }

            $calls[] = [
                'method' => $name,
                'signature' => $signature,
                'code' => $code,
                'note' => $note,
            ];
        }

        return $calls;
    }

    private function argumentsFromSignature(string $signature): string
    {
        if (!preg_match('/\((.*)\)/', $signature, $match)) {
            return '';
        }

        $inside = trim((string) $match[1]);
        if ($inside === '' || strtolower($inside) === 'void') {
            return '';
        }

        if (!preg_match_all('/\$[A-Za-z_][A-Za-z0-9_]*/', $inside, $matches)) {
            return '...';
        }

        return implode(', ', array_values(array_unique($matches[0])));
    }

    private function instanceVariable(string $fqcn, string $kind): string
    {
        if ($kind === 'interface') {
            return '$implementation';
        }

        $parts = explode('\\', trim($fqcn, '\\'));
        $shortName = (string) end($parts);
        $shortName = preg_replace('/[^A-Za-z0-9_]/', '', $shortName) ?: 'service';

        return '$' . lcfirst($shortName);
    }
}
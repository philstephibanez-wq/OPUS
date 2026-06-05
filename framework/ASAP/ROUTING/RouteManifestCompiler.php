<?php

declare(strict_types=1);

namespace ASAP\Routing;

/**
 * PUBLIC COMPILER
 *
 * Role:
 *   Compile explicit RouteDefinition objects into a runtime manifest.
 *
 * Responsibility:
 *   Merge route providers, sort routes, detect conflicts and write a PHP
 *   manifest optimized for runtime loading.
 *
 * Contract:
 *   Compilation is explicit. The autoloader may provide a class map, but this
 *   compiler is never invoked automatically during autoload.
 *
 * Since:
 *   P112Q1
 */
final class RouteManifestCompiler
{
    /**
     * @param RouteDefinition[] $routes
     * @return array<string,array<string,mixed>>
     */
    public function compile(array $routes): array
    {
        if ($routes === []) {
            throw RouteCompilerException::because('ASAP_ROUTE_COMPILER_NO_ROUTES');
        }

        usort(
            $routes,
            static fn (RouteDefinition $a, RouteDefinition $b): int => $b->priority <=> $a->priority ?: strcmp($a->name, $b->name)
        );

        $manifest = [];
        $nameIndex = [];
        $signatureIndex = [];

        foreach ($routes as $route) {
            if (!$route instanceof RouteDefinition) {
                throw RouteCompilerException::because('ASAP_ROUTE_COMPILER_INVALID_ROUTE_OBJECT');
            }

            if (isset($nameIndex[$route->name])) {
                throw RouteCompilerException::because('ASAP_ROUTE_NAME_DUPLICATE', $route->name);
            }

            $nameIndex[$route->name] = true;

            foreach ($route->normalizedMethods() as $method) {
                $signature = implode('|', [
                    $method,
                    $route->host ?? '*',
                    $route->locale ?? '*',
                    $route->path,
                ]);

                if (isset($signatureIndex[$signature])) {
                    throw RouteCompilerException::because('ASAP_ROUTE_PATH_METHOD_DUPLICATE', $signature);
                }

                $signatureIndex[$signature] = $route->name;
            }

            $manifest[$route->name] = $route->toManifestRow();
        }

        return $manifest;
    }

    /**
     * @param array<string,array<string,mixed>> $manifest
     */
    public function writePhpManifest(array $manifest, string $targetFile): void
    {
        if ($manifest === []) {
            throw RouteCompilerException::because('ASAP_ROUTE_MANIFEST_EMPTY');
        }

        $directory = dirname($targetFile);

        if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
            throw RouteCompilerException::because('ASAP_ROUTE_MANIFEST_DIR_CREATE_FAILED', $directory);
        }

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest, true) . ";\n";

        if (file_put_contents($targetFile, $php) === false) {
            throw RouteCompilerException::because('ASAP_ROUTE_MANIFEST_WRITE_FAILED', $targetFile);
        }
    }

    /** @return array<string,array<string,mixed>> */
    public function loadPhpManifest(string $manifestFile): array
    {
        if (!is_file($manifestFile)) {
            throw RouteCompilerException::because('ASAP_ROUTE_MANIFEST_MISSING', $manifestFile);
        }

        $manifest = require $manifestFile;

        if (!is_array($manifest) || $manifest === []) {
            throw RouteCompilerException::because('ASAP_ROUTE_MANIFEST_INVALID', $manifestFile);
        }

        return $manifest;
    }
}

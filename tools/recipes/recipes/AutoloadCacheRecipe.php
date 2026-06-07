<?php

declare(strict_types=1);

namespace ASAP\Recipe\Recipes;

use ASAP\Autoload\AutoloadCache;
use ASAP\Autoload\ClassMapBuilder;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC RECIPE: validate official ASAP autoloader cache and class index contract. */
final class AutoloadCacheRecipe implements RecipeInterface
{
    public function name(): string
    {
        return 'autoload_cache';
    }

    /** @return string[] */
    public function run(RecipeContext $context): array
    {
        $root = $context->rootPath();
        $cacheFile = AutoloadCache::defaultCacheFile($root);

        $builder = new ClassMapBuilder();
        $map = $builder->build($root);
        $builder->write($map, $cacheFile);

        $context->assert(is_file($cacheFile), 'ASAP_AUTOLOADER_CACHE_FILE_NOT_WRITTEN', $cacheFile);
        $context->assert((int)($map['class_count'] ?? 0) >= 40, 'ASAP_AUTOLOADER_CACHE_CLASS_COUNT_TOO_LOW', (string)($map['class_count'] ?? 0));
        $context->assert(($map['duplicates'] ?? []) === [], 'ASAP_AUTOLOADER_CACHE_DUPLICATES_PRESENT');

        (new AutoloadCache($root, $cacheFile))->register();

        foreach ([
            \ASAP\Autoload\AutoloadCache::class,
            \ASAP\Autoload\ClassMapBuilder::class,
            \ASAP\Core\Bootstrap::class,
            \ASAP\Application\Application::class,
            \ASAP\Routing\ClassIndex::class,
            \ASAP\Routing\RouteManifestCompiler::class,
            \ASAP\Lstsa\LstsaRunner::class,
            \ASAP\Database\DatabaseMultiConfigLoader::class,
        ] as $class) {
            $context->assert(class_exists($class), 'ASAP_AUTOLOADER_CACHE_CLASS_NOT_LOADABLE', $class);
        }

        $refBookRoot = dirname($root) . DIRECTORY_SEPARATOR . 'ASAP_REF_BOOK';
        if (is_dir($refBookRoot)) {
            $this->assertRefBookAutoloadSurface($context, $refBookRoot);
        } else {
            $context->diagnostic('ASAP_REFBOOK_NOT_PRESENT_FOR_AUTOLOAD_CACHE_CHECK=' . $refBookRoot);
        }

        $context->diagnostic('ASAP_AUTOLOADER_CACHE_FILE=' . $cacheFile);
        $context->diagnostic('ASAP_AUTOLOADER_CACHE_CLASS_COUNT=' . (string)$map['class_count']);

        return [
            'ASAP_AUTOLOADER_CACHE_BUILT_OK',
            'ASAP_AUTOLOADER_CACHE_CLASSES_OK',
            'ASAP_AUTOLOADER_CACHE_OK',
        ];
    }

    private function assertRefBookAutoloadSurface(RecipeContext $context, string $refBookRoot): void
    {
        $autoload = $refBookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $composerStatic = $refBookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_static.php';
        $asapVendorRoot = $refBookRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'logandplay' . DIRECTORY_SEPARATOR . 'asap';

        $context->assert(is_file($autoload), 'ASAP_REFBOOK_VENDOR_AUTOLOAD_MISSING', $autoload);
        $context->assert(is_file($composerStatic), 'ASAP_REFBOOK_COMPOSER_STATIC_MISSING', $composerStatic);
        $context->assert(is_dir($asapVendorRoot), 'ASAP_REFBOOK_ASAP_VENDOR_ROOT_MISSING', $asapVendorRoot);

        $static = (string)file_get_contents($composerStatic);
        $context->assert(str_contains($static, "'ASAP\\\\'") || str_contains($static, '"ASAP\\\\"'), 'ASAP_REFBOOK_ASAP_PSR4_PREFIX_MISSING', $composerStatic);

        $vendorFramework = $asapVendorRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';
        $context->assert(is_dir($vendorFramework), 'ASAP_REFBOOK_ASAP_VENDOR_FRAMEWORK_ASAP_MISSING', $vendorFramework);
    }
}

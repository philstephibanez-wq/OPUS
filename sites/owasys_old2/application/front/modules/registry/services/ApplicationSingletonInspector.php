<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** Reads and validates Singleton runtime contracts V1 and layered V2. */
final class OwasysApplicationSingletonInspector
{
    public const CONTRACT = 'OWASYS_APPLICATION_SINGLETON_INSPECTOR_V3';

    private static ?self $instance = null;

    private function __construct(private readonly string $opusRoot)
    {
    }

    public static function instance(string $opusRoot): self
    {
        $opusRoot = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->opusRoot !== $opusRoot) {
                throw new RuntimeException(
                    'OWASYS_SINGLETON_INSPECTOR_ROOT_MISMATCH'
                );
            }
            return self::$instance;
        }
        return self::$instance = new self($opusRoot);
    }

    /** @return array<string,mixed> */
    public function inspect(string $applicationRoot): array
    {
        $applicationRoot = trim(str_replace('\\', '/', $applicationRoot), '/');
        if ($applicationRoot === ''
            || !str_starts_with($applicationRoot, 'sites/')
            || str_contains($applicationRoot, '..')) {
            return $this->failure('OPUS_APPLICATION_ROOT_INVALID');
        }

        $absoluteRoot = $this->opusRoot . '/' . $applicationRoot;
        $siteConfigFile = $absoluteRoot . '/config/site.json';
        $file = File::instance();
        if (!$file->exists($siteConfigFile)) {
            return $this->failure('OPUS_APPLICATION_SITE_CONFIG_MISSING');
        }

        try {
            $site = StructuredFileLoader::instance()->read($siteConfigFile);
        } catch (Throwable $cause) {
            return $this->failure(
                'OPUS_APPLICATION_SITE_CONFIG_INVALID:' . $cause->getMessage()
            );
        }

        $runtime = is_array($site['runtime'] ?? null) ? $site['runtime'] : [];
        $contract = trim((string) ($runtime['contract'] ?? ''));
        return match ($contract) {
            'OPUS_APPLICATION_SINGLETON_V1' => $this->inspectV1(
                $absoluteRoot,
                $runtime,
                $file
            ),
            'OPUS_APPLICATION_SINGLETON_V2' => $this->inspectV2(
                $absoluteRoot,
                $runtime,
                $file
            ),
            default => $this->failure(
                'OPUS_APPLICATION_SINGLETON_CONTRACT_MISSING'
            ),
        };
    }

    /** @param array<string,mixed> $runtime @return array<string,mixed> */
    private function inspectV1(
        string $absoluteRoot,
        array $runtime,
        File $file
    ): array {
        $bootstrap = $this->safeRelative((string) (
            $runtime['bootstrap'] ?? 'application/default/bootstrap.php'
        ));
        if ($bootstrap === null) {
            return $this->failure('OPUS_APPLICATION_BOOTSTRAP_INVALID');
        }
        return $this->inspectRuntime(
            $absoluteRoot,
            $runtime,
            ['default' => $bootstrap],
            $file
        );
    }

    /** @param array<string,mixed> $runtime @return array<string,mixed> */
    private function inspectV2(
        string $absoluteRoot,
        array $runtime,
        File $file
    ): array {
        $declared = is_array($runtime['bootstraps'] ?? null)
            ? $runtime['bootstraps']
            : [];
        $bootstraps = [];
        foreach (['front', 'back'] as $mode) {
            $relative = $this->safeRelative((string) (
                $declared[$mode] ?? ''
            ));
            if ($relative === null) {
                return $this->failure(
                    'OPUS_APPLICATION_BOOTSTRAP_MISSING:' . $mode
                );
            }
            $bootstraps[$mode] = $relative;
        }
        return $this->inspectRuntime(
            $absoluteRoot,
            $runtime,
            $bootstraps,
            $file
        );
    }

    /**
     * @param array<string,mixed> $runtime
     * @param array<string,string> $bootstraps
     * @return array<string,mixed>
     */
    private function inspectRuntime(
        string $absoluteRoot,
        array $runtime,
        array $bootstraps,
        File $file
    ): array {
        $contract = trim((string) ($runtime['contract'] ?? ''));
        $architecture = trim((string) ($runtime['architecture'] ?? ''));
        $class = trim((string) ($runtime['class'] ?? ''));
        $classFile = $this->safeRelative((string) ($runtime['file'] ?? ''));
        $entrypoint = $this->safeRelative((string) (
            $runtime['entrypoint'] ?? ''
        ));
        $factory = trim((string) ($runtime['factory'] ?? ''));
        $runner = trim((string) ($runtime['runner'] ?? ''));

        if ($architecture !== 'singleton'
            || $class === ''
            || $classFile === null
            || $entrypoint === null
            || $factory !== 'instance'
            || $runner !== 'run') {
            return $this->failure(
                'OPUS_APPLICATION_SINGLETON_CONTRACT_MISSING'
            );
        }

        $required = [
            $absoluteRoot . '/' . $classFile
                => 'OPUS_APPLICATION_SINGLETON_CLASS_FILE_MISSING',
            $absoluteRoot . '/' . $entrypoint
                => 'OPUS_APPLICATION_ENTRYPOINT_MISSING',
        ];
        foreach ($bootstraps as $mode => $relative) {
            $required[$absoluteRoot . '/' . $relative] =
                'OPUS_APPLICATION_BOOTSTRAP_MISSING:' . $mode;
        }
        foreach ($required as $path => $error) {
            if (!$file->exists($path)) {
                return $this->failure($error);
            }
        }

        $classSource = $file->read($absoluteRoot . '/' . $classFile);
        $entrySource = $file->read($absoluteRoot . '/' . $entrypoint);
        $quotedClass = preg_quote($class, '/');
        $checks = [
            preg_match('/final\s+class\s+' . $quotedClass . '\b/', $classSource) === 1,
            str_contains($classSource, 'private static ?self $instance'),
            preg_match('/private\s+function\s+__construct\s*\(/', $classSource) === 1,
            preg_match('/public\s+static\s+function\s+instance\s*\(/', $classSource) === 1,
            preg_match('/public\s+function\s+run\s*\(/', $classSource) === 1,
            !preg_match('/\becho\b/', $entrySource),
            !str_contains($entrySource, '<html'),
        ];
        foreach ($bootstraps as $relative) {
            $bootstrapSource = $file->read($absoluteRoot . '/' . $relative);
            $checks[] = str_contains(
                $bootstrapSource,
                $class . '::instance('
            );
            $checks[] = str_contains($bootstrapSource, ')->run();');
            $checks[] = str_contains(
                str_replace('\\', '/', $entrySource),
                str_replace('\\', '/', $relative)
            );
        }
        if (in_array(false, $checks, true)) {
            return $this->failure(
                'OPUS_APPLICATION_SINGLETON_IMPLEMENTATION_INVALID'
            );
        }

        return [
            'contract' => $contract,
            'architecture' => $architecture,
            'class' => $class,
            'file' => $classFile,
            'bootstraps' => $bootstraps,
            'entrypoint' => $entrypoint,
            'compliant' => true,
            'error' => '',
        ];
    }

    private function safeRelative(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }
        return $path;
    }

    /** @return array<string,mixed> */
    private function failure(string $error): array
    {
        return [
            'contract' => '',
            'architecture' => '',
            'class' => '',
            'file' => '',
            'bootstraps' => [],
            'entrypoint' => '',
            'compliant' => false,
            'error' => $error,
        ];
    }
}

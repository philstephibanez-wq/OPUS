<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** Reads and validates the Singleton runtime contract of autonomous OPUS sites. */
final class OwasysApplicationSingletonInspector
{
    public const CONTRACT = 'OWASYS_APPLICATION_SINGLETON_INSPECTOR_V2';

    private static ?self $instance = null;

    private function __construct(private readonly string $opusRoot)
    {
    }

    public static function instance(string $opusRoot): self
    {
        $opusRoot = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->opusRoot !== $opusRoot) {
                throw new RuntimeException('OWASYS_SINGLETON_INSPECTOR_ROOT_MISMATCH');
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
        $architecture = trim((string) ($runtime['architecture'] ?? ''));
        $class = trim((string) ($runtime['class'] ?? ''));
        $classFile = $this->safeRelative((string) ($runtime['file'] ?? ''));
        $entrypoint = $this->safeRelative((string) ($runtime['entrypoint'] ?? ''));
        $bootstrap = $this->safeRelative((string) (
            $runtime['bootstrap'] ?? 'application/default/bootstrap.php'
        ));
        $factory = trim((string) ($runtime['factory'] ?? ''));
        $runner = trim((string) ($runtime['runner'] ?? ''));

        if ($contract !== 'OPUS_APPLICATION_SINGLETON_V1'
            || $architecture !== 'singleton'
            || $class === ''
            || $classFile === null
            || $entrypoint === null
            || $bootstrap === null
            || $factory !== 'instance'
            || $runner !== 'run') {
            return $this->failure('OPUS_APPLICATION_SINGLETON_CONTRACT_MISSING');
        }

        $classPath = $absoluteRoot . '/' . $classFile;
        $entryPath = $absoluteRoot . '/' . $entrypoint;
        $bootstrapPath = $absoluteRoot . '/' . $bootstrap;
        foreach ([
            $classPath => 'OPUS_APPLICATION_SINGLETON_CLASS_FILE_MISSING',
            $entryPath => 'OPUS_APPLICATION_ENTRYPOINT_MISSING',
            $bootstrapPath => 'OPUS_APPLICATION_BOOTSTRAP_MISSING',
        ] as $path => $error) {
            if (!$file->exists($path)) {
                return $this->failure($error);
            }
        }

        $classSource = $file->read($classPath);
        $entrySource = $file->read($entryPath);
        $bootstrapSource = $file->read($bootstrapPath);
        $quotedClass = preg_quote($class, '/');
        $bootstrapReference = str_replace('\\', '/', $bootstrap);

        $checks = [
            preg_match('/final\s+class\s+' . $quotedClass . '\b/', $classSource) === 1,
            str_contains($classSource, 'private static ?self $instance'),
            preg_match('/private\s+function\s+__construct\s*\(/', $classSource) === 1,
            preg_match('/public\s+static\s+function\s+instance\s*\(/', $classSource) === 1,
            preg_match('/public\s+function\s+run\s*\(/', $classSource) === 1,
            str_contains($bootstrapSource, $class . '::instance('),
            str_contains($bootstrapSource, ')->run();'),
            str_contains(str_replace('\\', '/', $entrySource), basename($bootstrapReference)),
            !preg_match('/\becho\b/', $entrySource),
            !str_contains($entrySource, '<html'),
        ];

        if (in_array(false, $checks, true)) {
            return $this->failure('OPUS_APPLICATION_SINGLETON_IMPLEMENTATION_INVALID');
        }

        return [
            'contract' => $contract,
            'architecture' => $architecture,
            'class' => $class,
            'file' => $classFile,
            'bootstrap' => $bootstrap,
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
            'bootstrap' => '',
            'entrypoint' => '',
            'compliant' => false,
            'error' => $error,
        ];
    }
}

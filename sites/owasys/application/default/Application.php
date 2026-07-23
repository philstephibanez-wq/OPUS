<?php
declare(strict_types=1);

/** Singleton composition root for the autonomous OWASYS application. */
final class OwasysApplication
{
    public const CONTRACT = 'OWASYS_APPLICATION_SINGLETON_V1';

    private static ?self $instance = null;

    private readonly OwasysRuntimeController $frontend;
    private readonly OwasysBackendApiController $backend;

    /** @param array<string,mixed> $siteConfig */
    private function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig
    ) {
        $opusRoot = dirname(dirname($this->siteRoot));
        $session = new OwasysAuthSession();
        $security = new OwasysRuntimeSecurity($siteRoot, $siteConfig);

        $this->frontend = new OwasysRuntimeController(
            $siteRoot,
            $siteConfig,
            $session,
            $security,
            new OwasysScorePageRenderer($siteRoot)
        );
        $this->backend = new OwasysBackendApiController(
            $siteRoot,
            $opusRoot
        );
    }

    /** @param array<string,mixed> $siteConfig */
    public static function instance(
        string $siteRoot,
        array $siteConfig
    ): self {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');

        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException(
                    'OWASYS_APPLICATION_SINGLETON_ROOT_MISMATCH'
                );
            }
            return self::$instance;
        }

        return self::$instance = new self($siteRoot, $siteConfig);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException(
            'OWASYS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN'
        );
    }

    public function run(): void
    {
        if ($this->backend->matchesCurrentRequest()) {
            $this->backend->run();
            return;
        }
        $this->frontend->run();
    }
}

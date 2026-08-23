<?php
declare(strict_types=1);

use Opus\Application\Runtime\GeneratedSiteRuntime;
use Opus\Http\Response;

final class EssaiApplication implements EssaiApplicationInterface
{
    private static ?self $instance = null;
    private readonly GeneratedSiteRuntime $runtime;

    private function __construct(private readonly string $siteRoot)
    {
        $this->runtime = new GeneratedSiteRuntime($siteRoot);
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException('OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH');
            }
            return self::$instance;
        }
        return self::$instance = new self($siteRoot);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN');
    }

    public function handle(): Response
    {
        return $this->runtime->handle();
    }

    public function run(): void
    {
        $this->handle()->send();
    }
}

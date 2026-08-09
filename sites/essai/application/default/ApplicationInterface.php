<?php
declare(strict_types=1);

interface EssaiApplicationInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function instance(string $siteRoot): self;
    public function run(): void;
}

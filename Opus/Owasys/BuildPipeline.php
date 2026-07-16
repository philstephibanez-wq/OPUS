<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Executes the OWASYS delivery chain for one application request.
 *
 * The pipeline keeps preview, write, validation and export in one explicit
 * contract so the OWASYS Build screen can call the same implementation as CLI.
 */
final class BuildPipeline
{
    public const CONTRACT = 'OWASYS_BUILD_PIPELINE_RESULT_V1';

    public function __construct(
        private readonly string $opusRoot,
        private readonly ?ApplicationCreator $creator = null,
        private readonly ?ApplicationExporter $exporter = null
    ) {
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function run(
        array $request,
        string $mode = 'preview',
        ?string $outputZip = null,
        bool $overwrite = false
    ): array {
        if (!in_array($mode, ['preview', 'build', 'build-and-export'], true)) {
            throw new RuntimeException('OWASYS_BUILD_PIPELINE_MODE_INVALID');
        }

        $creator = $this->creator ?? new ApplicationCreator($this->opusRoot);
        $write = $mode !== 'preview';
        $creation = $creator->create($request, $write, $write);

        $result = [
            'contract' => self::CONTRACT,
            'mode' => $mode,
            'site_id' => (string) ($creation['site_id'] ?? ''),
            'site_root' => (string) ($creation['site_root'] ?? ''),
            'creation' => $creation,
            'export' => null,
        ];

        if ($mode !== 'build-and-export') {
            return $result;
        }

        $siteId = (string) ($creation['site_id'] ?? '');
        if ($siteId === '') {
            throw new RuntimeException('OWASYS_BUILD_PIPELINE_SITE_ID_MISSING');
        }

        $target = $outputZip ?? ('var/owasys-export/' . $siteId . '.zip');
        $exporter = $this->exporter ?? new ApplicationExporter($this->opusRoot);
        $result['export'] = $exporter->export($siteId, $target, $overwrite);

        return $result;
    }
}

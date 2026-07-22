<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Stage 02: Securize source data before transformation.
 *
 * Historical filename is 02_Secure.php; canonical stage name is securize.
 */
final class SecurizeStage implements LstsarStageInterface, SecurizeStageInterface
{
    public function name(): string
    {
        return LstsarStageName::SECURIZE;
    }

    public function execute(LstsarContext $context): LstsarStageResult
    {
        $security = $context->config()->security();
        if (($security['acl_granted'] ?? true) !== true) {
            return LstsarStageResult::rejected($this->name(), [
                new LstsarViolation($this->name(), '*', 'OPUS_LSTSAR_SECURIZE_DENIED', 'LSTSAR security policy denied the run.', $security),
            ], [[
                'stage' => $this->name(),
                'code' => 'OPUS_LSTSAR_SECURIZE_DENIED',
            ]]);
        }

        return LstsarStageResult::success($this->name(), [
            'policy' => $security['policy'] ?? 'explicit-or-default',
            'granted' => true,
        ], [[
            'stage' => $this->name(),
            'code' => 'OPUS_LSTSAR_SECURIZE_GRANTED',
        ]]);
    }
}

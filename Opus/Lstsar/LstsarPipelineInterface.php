<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Contract implemented by every LSTSAR pipeline.
 *
 * LSTSAR means Load / Secure / Transform / Store / Audit / Report.
 * The pipeline is a framework contract and must consume OPUS Security Core rather than
 * embedding authentication, ACL or FSM logic directly.
 */
interface LstsarPipelineInterface
{
    public function id(): string;

    /**
     * @return list<string>
     */
    public function stageOrder(): array;

    /**
     * @return array<string,mixed>
     */
    public function describe(): array;
}

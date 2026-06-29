<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Writes one transformed destination record for a model-driven LSTSAR run.
 */
interface LstsarOdbcDestinationWriterInterface
{
    /** @param array<string,mixed> $record */
    public function store(LstsarConfig $config, TableModel $destinationModel, array $record): string;
}

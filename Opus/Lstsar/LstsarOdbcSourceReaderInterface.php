<?php
declare(strict_types=1);

namespace Opus\Lstsar;

use Opus\Model\TableModel;

/**
 * Reads one source record for a model-driven LSTSAR run from an ODBC-facing source.
 */
interface LstsarOdbcSourceReaderInterface
{
    /** @return array<string,mixed> */
    public function load(LstsarConfig $config, TableModel $sourceModel): array;
}

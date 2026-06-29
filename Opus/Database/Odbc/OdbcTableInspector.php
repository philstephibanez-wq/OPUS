<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

use Opus\Model\Adapter\OdbcModelAdapter;
use Opus\Model\TableModel;

/**
 * Builds OPUS table models from ODBC metadata.
 */
final class OdbcTableInspector
{
    private OdbcConnectionInterface $connection;

    public function __construct(OdbcConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function inspectTable(string $modelId, string $table): TableModel
    {
        $adapter = new OdbcModelAdapter($this->connection);

        return $adapter->tableToModel($modelId, $table);
    }
}

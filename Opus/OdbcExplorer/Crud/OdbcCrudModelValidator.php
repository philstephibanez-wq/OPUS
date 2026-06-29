<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

/**
 * Model-based validation gate before any ODBC CRUD execution.
 */
final class OdbcCrudModelValidator
{
    /** @param array<string,mixed> $values @return list<string> */
    public function validateValues(TableModel $model, array $values): array
    {
        try {
            new ModelRecord($model, $values);
        } catch (\Throwable $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }

    /** @param array<string,mixed> $values */
    public function assertValuesValid(TableModel $model, array $values): ModelRecord
    {
        return new ModelRecord($model, $values);
    }
}

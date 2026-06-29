<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

use Opus\Model\TableModel;

/**
 * Immutable guarded CRUD command. This is intentionally not raw SQL.
 */
final class OdbcCrudCommand
{
    private string $action;
    private TableModel $model;
    /** @var array<string,mixed> */
    private array $values;
    private OdbcCrudPredicate $predicate;
    private string $actorId;
    private string $confirmationToken;
    private string $requestId;

    /** @param array<string,mixed> $values */
    private function __construct(string $action, TableModel $model, array $values, OdbcCrudPredicate $predicate, string $actorId, string $confirmationToken, string $requestId)
    {
        $action = OdbcCrudAction::assertSupported($action);
        $actorId = trim($actorId);
        $confirmationToken = trim($confirmationToken);
        $requestId = trim($requestId);

        if ($actorId === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_ACTOR_EMPTY');
        }
        if ($requestId === '') {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_REQUEST_EMPTY');
        }
        if (($action === OdbcCrudAction::INSERT || $action === OdbcCrudAction::UPDATE) && $values === []) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_VALUES_EMPTY: ' . $action);
        }
        if ($action === OdbcCrudAction::DELETE && $values !== []) {
            throw new \InvalidArgumentException('OPUS_ODBC_CRUD_DELETE_VALUES_FORBIDDEN');
        }

        (new OdbcCrudModelValidator())->assertValuesValid($model, $values);
        $predicate->assertNotEmptyFor($action);

        $this->action = $action;
        $this->model = $model;
        $this->values = $values;
        $this->predicate = $predicate;
        $this->actorId = $actorId;
        $this->confirmationToken = $confirmationToken;
        $this->requestId = $requestId;
    }

    /** @param array<string,mixed> $values */
    public static function insert(TableModel $model, array $values, string $actorId, string $confirmationToken, string $requestId): self
    {
        return new self(OdbcCrudAction::INSERT, $model, $values, new OdbcCrudPredicate(), $actorId, $confirmationToken, $requestId);
    }

    /** @param array<string,mixed> $values */
    public static function update(TableModel $model, array $values, OdbcCrudPredicate $predicate, string $actorId, string $confirmationToken, string $requestId): self
    {
        return new self(OdbcCrudAction::UPDATE, $model, $values, $predicate, $actorId, $confirmationToken, $requestId);
    }

    public static function delete(TableModel $model, OdbcCrudPredicate $predicate, string $actorId, string $confirmationToken, string $requestId): self
    {
        return new self(OdbcCrudAction::DELETE, $model, [], $predicate, $actorId, $confirmationToken, $requestId);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function model(): TableModel
    {
        return $this->model;
    }

    public function tableName(): string
    {
        return $this->model->tableName();
    }

    /** @return array<string,mixed> */
    public function values(): array
    {
        return $this->values;
    }

    public function predicate(): OdbcCrudPredicate
    {
        return $this->predicate;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function confirmationToken(): string
    {
        return $this->confirmationToken;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /** @return array<string,mixed> */
    public function auditContext(): array
    {
        return [
            'action' => $this->action,
            'table' => $this->tableName(),
            'model' => $this->model->id(),
            'actor' => $this->actorId,
            'request_id' => $this->requestId,
            'value_fields' => array_keys($this->values),
            'predicate_fields' => array_keys($this->predicate->criteria()),
        ];
    }
}

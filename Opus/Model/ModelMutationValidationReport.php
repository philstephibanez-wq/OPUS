<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Validation report for a Model write intent.
 */
final class ModelMutationValidationReport
{
    private string $intent;
    private string $modelId;
    /** @var list<array<string,string>> */
    private array $errors = [];
    /** @var list<array<string,string>> */
    private array $warnings = [];

    public function __construct(string $intent, string $modelId)
    {
        $this->intent = ModelMutationIntent::assertSupported($intent);
        $modelId = trim($modelId);
        if ($modelId === '') {
            throw new \InvalidArgumentException('OPUS_MODEL_MUTATION_REPORT_MODEL_EMPTY');
        }
        $this->modelId = $modelId;
    }

    public function addError(string $code, string $field = '', string $message = ''): void
    {
        $this->errors[] = [
            'code' => trim($code),
            'field' => trim($field),
            'message' => trim($message),
        ];
    }

    public function addWarning(string $code, string $field = '', string $message = ''): void
    {
        $this->warnings[] = [
            'code' => trim($code),
            'field' => trim($field),
            'message' => trim($message),
        ];
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<array<string,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<array<string,string>> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function assertValid(): void
    {
        if ($this->isValid()) {
            return;
        }

        $codes = array_map(static fn (array $error): string => $error['code'] . ($error['field'] !== '' ? ':' . $error['field'] : ''), $this->errors);
        throw new \RuntimeException('OPUS_MODEL_MUTATION_INVALID: ' . $this->intent . ':' . implode(',', $codes));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => 'OPUS_MODEL_MUTATION_VALIDATION_REPORT_V1',
            'intent' => $this->intent,
            'model' => $this->modelId,
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}

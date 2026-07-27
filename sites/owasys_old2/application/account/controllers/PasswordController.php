<?php
declare(strict_types=1);

final class OwasysPasswordController
{
    public function __construct(private readonly OwasysPasswordModel $model)
    {
    }

    /** @return array<string,mixed> */
    public function handle(string $method, array $input): array
    {
        if ($method !== 'POST') {
            return ['error' => null];
        }

        if (($input['owasys_action'] ?? null) !== 'change-password') {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_ACTION_INVALID');
        }

        return $this->model->change(
            (string) ($input['owasys_current_password'] ?? ''),
            (string) ($input['owasys_new_password'] ?? ''),
            (string) ($input['owasys_confirm_password'] ?? '')
        );
    }
}

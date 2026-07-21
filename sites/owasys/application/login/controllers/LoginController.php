<?php
declare(strict_types=1);

final class OwasysLoginController
{
    public function __construct(private readonly OwasysLoginModel $model)
    {
    }

    /** @return array<string,mixed> */
    public function handle(string $method, array $input): array
    {
        if ($method !== 'POST') {
            return ['error' => null];
        }

        if (($input['owasys_action'] ?? null) !== 'password-signin') {
            throw new RuntimeException('OWASYS_LOGIN_ACTION_INVALID');
        }

        return $this->model->authenticate(
            trim((string) ($input['owasys_username'] ?? '')),
            (string) ($input['owasys_password'] ?? '')
        );
    }
}

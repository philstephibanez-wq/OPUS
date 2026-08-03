<?php
declare(strict_types=1);

final class OwasysRegistryController
{
    public function __construct(private readonly OwasysRegistryModel $model)
    {
    }

    /** @return array<string,mixed> */
    public function handle(string $method, array $post): array
    {
        $sync = $this->model->synchronize();
        $signal = null;
        $selectedApp = null;
        $error = null;

        if ($method === 'POST') {
            $action = trim((string) ($post['owasys_action'] ?? ''));

            if ($action === 'select-app') {
                $applicationId = trim((string) ($post['owasys_app_id'] ?? ''));
                if ($applicationId === '') {
                    $error = 'registry.error.application_required';
                } else {
                    $selectedApp = $this->model->find($applicationId);
                    if ($selectedApp === null) {
                        $error = 'registry.error.application_not_found';
                    } else {
                        $signal = 'select_app';
                    }
                }
            } elseif ($action === 'clear-app-context') {
                $signal = 'clear_app_context';
            } elseif ($action === 'create-new-app') {
                $signal = 'create_new_app';
            } elseif ($action === 'delete-app') {
                $applicationId = trim((string) ($post['owasys_app_id'] ?? ''));
                $confirmation = trim((string) (
                    $post['owasys_delete_confirmation'] ?? ''
                ));
                if ($applicationId === '') {
                    $error = 'registry.error.application_required';
                } elseif ($this->model->find($applicationId) === null) {
                    $error = 'registry.error.application_not_found';
                } elseif (in_array(
                    $applicationId,
                    ['owasys-front', 'owasys-back'],
                    true
                )) {
                    $error = 'registry.error.application_protected';
                } elseif (!hash_equals($applicationId, $confirmation)) {
                    $error = 'registry.error.delete_confirmation';
                } else {
                    $this->model->delete(
                        $applicationId,
                        $confirmation,
                        $this->sessionActor()
                    );
                    $sync = $this->model->synchronize();
                    $signal = 'application_deleted';
                }
            } else {
                $error = 'registry.error.action_invalid';
            }
        }

        if ($error !== null) {
            $signal = 'registry_action_failed';
        }

        return [
            'sync' => $sync,
            'entries' => $this->model->entries(),
            'recent_events' => $this->model->recentEvents(8),
            'signal' => $signal,
            'selected_app' => $selectedApp,
            'error' => $error,
        ];
    }

    /** @return array<string,mixed> */
    private function sessionActor(): array
    {
        $session = new OwasysAuthSession();
        $actor = $session->user();
        if (!is_array($actor)) {
            throw new RuntimeException('OWASYS_REGISTRY_AUTH_REQUIRED');
        }
        return $actor;
    }
}

<?php
declare(strict_types=1);

final class OwasysRegistryController
{
    public function __construct(private readonly OwasysRegistryModel $model)
    {
    }

    /** @return array<string,mixed> */
    public function handle(string $method, array $post, string $actorId): array
    {
        $sync = $this->model->synchronize();
        $event = null;
        $redirect = null;
        $selectedApp = null;
        $error = null;

        if ($method === 'POST') {
            $action = trim((string) ($post['owasys_action'] ?? ''));

            if ($action === 'select-app') {
                $applicationId = trim((string) ($post['owasys_app_id'] ?? ''));

                if ($applicationId === '') {
                    $error = 'registry.error.application_required';
                } else {
                    $selectedApp = $this->model->select($applicationId, $actorId);

                    if ($selectedApp === null) {
                        $error = 'registry.error.application_not_found';
                    } else {
                        $event = 'select_app';
                        $redirect = 'structure';
                    }
                }
            } elseif ($action === 'clear-app-context') {
                $this->model->clear($actorId);
                $event = 'clear_app_context';
                $redirect = 'applications';
            } elseif ($action === 'create-new-app') {
                $this->model->startCreation($actorId);
                $event = 'create_new_app';
                $redirect = 'build';
            } elseif ($action !== '') {
                $error = 'registry.error.action_invalid';
            }
        }

        return [
            'sync' => $sync,
            'entries' => $this->model->entries(),
            'recent_events' => $this->model->recentEvents(8),
            'event' => $event,
            'redirect' => $redirect,
            'selected_app' => $selectedApp,
            'error' => $error,
        ];
    }
}

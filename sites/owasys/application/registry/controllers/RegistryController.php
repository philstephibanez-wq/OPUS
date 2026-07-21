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
        $event = null;
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
                        $event = 'select_app';
                    }
                }
            } elseif ($action === 'clear-app-context') {
                $event = 'clear_app_context';
            } elseif ($action === 'create-new-app') {
                $event = 'create_new_app';
            } else {
                $error = 'registry.error.action_invalid';
            }
        }

        if ($error !== null) {
            $event = 'registry_action_failed';
        }

        return [
            'sync' => $sync,
            'entries' => $this->model->entries(),
            'recent_events' => $this->model->recentEvents(8),
            'event' => $event,
            'selected_app' => $selectedApp,
            'error' => $error,
        ];
    }
}

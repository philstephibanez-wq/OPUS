<?php
declare(strict_types=1);

use Opus\Fsm\FsmActionDispatcher;
use Opus\Fsm\FsmProcessor;
use Opus\Security\Sso\SsoIdentity;

/**
 * Application-owned source for developer-programmed OWASYS EFSM handlers.
 *
 * Handler entries between the explicit OPUS_EFSM_HANDLER_REGION markers are
 * real PHP callables. The developer designer edits only those managed entries;
 * no eval/runtime source compilation is used.
 */
final class OwasysFsmDeveloperHandlers
{
    private ?SsoIdentity $updatedIdentity = null;

    public function __construct(
        private readonly OwasysRuntimeSecurity $security,
        private readonly ?OwasysAuthSession $session = null,
        private readonly ?OwasysRegistryModel $registry = null
    ) {
    }

    /** @return array<string,callable> */
    public function guards(): array
    {
        return [
            /* OPUS_EFSM_HANDLER_REGION_BEGIN:guard */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:always */
            'always' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $transition,
                    $context,
                    $processor
                );
                return true;
            },
            /* OPUS_EFSM_HANDLER_END:guard:always */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:route_exists */
            'route_exists' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $context
                );
                $target = (string) (
                    $transition['next_state'] ?? ''
                );
                if ($target === ''
                    || !$processor->hasState($target)) {
                    return false;
                }
                return (string) (
                    $processor->state($target)['route'] ?? ''
                ) !== '';
            },
            /* OPUS_EFSM_HANDLER_END:guard:route_exists */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:app_exists */
            'app_exists' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $transition,
                    $processor
                );
                return ($context['app_exists'] ?? null) === true
                    || is_array(
                        $context['registry_entry'] ?? null
                    )
                    || is_array(
                        $context['selected_app'] ?? null
                    )
                    || (string) (
                        $context['selected_app'] ?? ''
                    ) !== '';
            },
            /* OPUS_EFSM_HANDLER_END:guard:app_exists */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:current_app_required */
            'current_app_required' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $transition,
                    $processor
                );
                $currentApp = $context['current_app'] ?? null;
                return ($context['has_current_app'] ?? null)
                    === true
                    || (
                        is_array($currentApp)
                        && $currentApp !== []
                    )
                    || (
                        is_string($currentApp)
                        && $currentApp !== ''
                    );
            },
            /* OPUS_EFSM_HANDLER_END:guard:current_app_required */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:current_app_or_creation_request */
            'current_app_or_creation_request' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $transition,
                    $processor
                );
                $currentApp = $context['current_app'] ?? null;
                $hasCurrentApp =
                    ($context['has_current_app'] ?? null) === true
                    || (
                        is_array($currentApp)
                        && $currentApp !== []
                    )
                    || (
                        is_string($currentApp)
                        && $currentApp !== ''
                    );
                return $hasCurrentApp
                    || is_array(
                        $context['creation_request'] ?? null
                    )
                    || (
                        $context['creation_request_started']
                            ?? null
                    ) === true;
            },
            /* OPUS_EFSM_HANDLER_END:guard:current_app_or_creation_request */
            /* OPUS_EFSM_HANDLER_BEGIN:guard:must_change_password */
            'must_change_password' => function (
                string $currentState,
                string $signal,
                array $transition,
                array $context,
                FsmProcessor $processor
            ): bool {
                unset(
                    $currentState,
                    $signal,
                    $transition,
                    $processor
                );
                return (
                    $context['must_change_password'] ?? null
                ) === true;
            },
            /* OPUS_EFSM_HANDLER_END:guard:must_change_password */
            /* OPUS_EFSM_HANDLER_REGION_END:guard */
        ];
    }

    /** @return array<string,callable> */
    public function actions(): array
    {
        return [
            /* OPUS_EFSM_HANDLER_REGION_BEGIN:action */
            /* OPUS_EFSM_HANDLER_BEGIN:action:start_session */
            'start_session' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): array {
                unset($action, $transition, $dispatcher);
                $identity = $context['pending_identity'] ?? null;
                if (!$identity instanceof SsoIdentity) {
                    throw new RuntimeException(
                        'OWASYS_FSM_PENDING_IDENTITY_MISSING'
                    );
                }
                $session = $identity->toSession();
                $this->session()->start($session);
                return $session;
            },
            /* OPUS_EFSM_HANDLER_END:action:start_session */
            /* OPUS_EFSM_HANDLER_BEGIN:action:clear_session */
            'clear_session' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): bool {
                unset($action, $transition, $context, $dispatcher);
                $this->session()->clearIdentity();
                return true;
            },
            /* OPUS_EFSM_HANDLER_END:action:clear_session */
            /* OPUS_EFSM_HANDLER_BEGIN:action:set_current_app */
            'set_current_app' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): array {
                unset($action, $transition, $dispatcher);
                $application = $context['selected_app'] ?? null;
                if (!is_array($application)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_SELECTED_APP_MISSING'
                    );
                }
                $this->registry()->setCurrent(
                    $application,
                    $this->actor($context)
                );
                $this->session()->setCurrentApp($application);
                return $application;
            },
            /* OPUS_EFSM_HANDLER_END:action:set_current_app */
            /* OPUS_EFSM_HANDLER_BEGIN:action:clear_current_app */
            'clear_current_app' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): bool {
                unset($action, $transition, $dispatcher);
                if ($this->registry instanceof OwasysRegistryModel
                    && is_array($this->session()->currentApp())) {
                    $this->registry->clear($this->actor($context));
                }
                $this->session()->clearCurrentApp();
                return true;
            },
            /* OPUS_EFSM_HANDLER_END:action:clear_current_app */
            /* OPUS_EFSM_HANDLER_BEGIN:action:clear_deleted_app_context */
            'clear_deleted_app_context' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): bool {
                unset($action, $transition, $dispatcher);
                $deletedId = trim((string) (
                    $context['deleted_app_id'] ?? ''
                ));
                if ($deletedId === '') {
                    throw new RuntimeException(
                        'OWASYS_FSM_DELETED_APP_ID_MISSING'
                    );
                }
                $current = $this->session()->currentApp();
                if (!is_array($current)
                    || (string) ($current['id'] ?? '') !== $deletedId) {
                    return true;
                }
                if ($this->registry instanceof OwasysRegistryModel) {
                    $this->registry->clear($this->actor($context));
                }
                $this->session()->clearCurrentApp();
                return true;
            },
            /* OPUS_EFSM_HANDLER_END:action:clear_deleted_app_context */
            /* OPUS_EFSM_HANDLER_BEGIN:action:update_runtime_password_hash */
            'update_runtime_password_hash' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): array {
                unset($action, $transition, $dispatcher);
                $identity = $context['identity'] ?? null;
                $post = $context['post'] ?? null;
                if (!is_array($identity) || !is_array($post)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_PASSWORD_CONTEXT_MISSING'
                    );
                }
                $this->updatedIdentity = $this->security->changePassword(
                    $identity,
                    $post
                );
                return $this->updatedIdentity->toSession();
            },
            /* OPUS_EFSM_HANDLER_END:action:update_runtime_password_hash */
            /* OPUS_EFSM_HANDLER_BEGIN:action:clear_must_change_password */
            'clear_must_change_password' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): array {
                unset($action, $transition, $context, $dispatcher);
                if (!$this->updatedIdentity instanceof SsoIdentity) {
                    throw new RuntimeException(
                        'OWASYS_FSM_UPDATED_IDENTITY_MISSING'
                    );
                }
                $session = $this->updatedIdentity->toSession();
                $session['must_change_password'] = false;
                $this->session()->update($session);
                return $session;
            },
            /* OPUS_EFSM_HANDLER_END:action:clear_must_change_password */
            /* OPUS_EFSM_HANDLER_BEGIN:action:redirect_password_change */
            'redirect_password_change' => function (
                string $action,
                array $transition,
                array $context,
                FsmActionDispatcher $dispatcher
            ): bool {
                unset($action, $transition, $context, $dispatcher);
                return true;
            },
            /* OPUS_EFSM_HANDLER_END:action:redirect_password_change */
            /* OPUS_EFSM_HANDLER_REGION_END:action */
        ];
    }

    private function session(): OwasysAuthSession
    {
        if (!$this->session instanceof OwasysAuthSession) {
            throw new RuntimeException(
                'OWASYS_FSM_SESSION_HANDLER_UNAVAILABLE'
            );
        }
        return $this->session;
    }

    private function registry(): OwasysRegistryModel
    {
        if (!$this->registry instanceof OwasysRegistryModel) {
            throw new RuntimeException(
                'OWASYS_FSM_REGISTRY_HANDLER_UNAVAILABLE'
            );
        }
        return $this->registry;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function actor(array $context): array
    {
        $identity = is_array($context['identity'] ?? null)
            ? $context['identity']
            : [];
        $subject = trim((string) (
            $identity['subject'] ?? $identity['id'] ?? ''
        ));
        $roles = is_array($identity['roles'] ?? null)
            ? array_values(array_filter(
                $identity['roles'],
                'is_string'
            ))
            : [];
        if ($subject === '' || $roles === []) {
            throw new RuntimeException(
                'OWASYS_FSM_ACTOR_INVALID'
            );
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => trim((string) (
                $identity['provider'] ?? 'owasys-sso'
            )),
        ];
    }
}
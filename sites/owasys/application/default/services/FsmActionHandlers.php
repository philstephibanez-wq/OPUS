<?php
declare(strict_types=1);

use Opus\Fsm\FsmActionDispatcher;
use Opus\Security\Sso\SsoIdentity;

final class OwasysFsmActionHandlers
{
    private ?SsoIdentity $updatedIdentity = null;

    public function __construct(
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly ?OwasysRegistryModel $registry
    ) {
    }

    public function dispatcher(): FsmActionDispatcher
    {
        return new FsmActionDispatcher([
            'start_session' => fn (string $action, array $transition, array $context): array => $this->startSession($context),
            'clear_session' => fn (string $action, array $transition, array $context): bool => $this->clearSession(),
            'set_current_app' => fn (string $action, array $transition, array $context): array => $this->setCurrentApp($context),
            'clear_current_app' => fn (string $action, array $transition, array $context): bool => $this->clearCurrentApp($context),
            'start_creation_flow' => fn (string $action, array $transition, array $context): bool => $this->startCreationFlow($context),
            'update_runtime_password_hash' => fn (string $action, array $transition, array $context): array => $this->updatePassword($context),
            'clear_must_change_password' => fn (string $action, array $transition, array $context): array => $this->clearMustChangePassword(),
            'redirect_password_change' => static fn (string $action, array $transition, array $context): bool => true,
        ]);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function startSession(array $context): array
    {
        $identity = $context['pending_identity'] ?? null;
        if (!$identity instanceof SsoIdentity) {
            throw new RuntimeException('OWASYS_FSM_PENDING_IDENTITY_MISSING');
        }
        $session = $identity->toSession();
        $this->session->start($session);
        return $session;
    }

    private function clearSession(): bool
    {
        $this->session->clearIdentity();
        return true;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function setCurrentApp(array $context): array
    {
        $application = $context['selected_app'] ?? null;
        if (!is_array($application)) {
            throw new RuntimeException('OWASYS_FSM_SELECTED_APP_MISSING');
        }
        $this->registry()->setCurrent($application, $this->actor($context));
        $this->session->setCurrentApp($application);
        return $application;
    }

    /** @param array<string,mixed> $context */
    private function clearCurrentApp(array $context): bool
    {
        if ($this->registry instanceof OwasysRegistryModel && is_array($this->session->currentApp())) {
            $this->registry->clear($this->actor($context));
        }
        $this->session->clearCurrentApp();
        return true;
    }

    /** @param array<string,mixed> $context */
    private function startCreationFlow(array $context): bool
    {
        $this->registry()->startCreation($this->actor($context));
        return true;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function updatePassword(array $context): array
    {
        $identity = $context['identity'] ?? null;
        $post = $context['post'] ?? null;
        if (!is_array($identity) || !is_array($post)) {
            throw new RuntimeException('OWASYS_FSM_PASSWORD_CONTEXT_MISSING');
        }
        $this->updatedIdentity = $this->security->changePassword($identity, $post);
        return $this->updatedIdentity->toSession();
    }

    /** @return array<string,mixed> */
    private function clearMustChangePassword(): array
    {
        if (!$this->updatedIdentity instanceof SsoIdentity) {
            throw new RuntimeException('OWASYS_FSM_UPDATED_IDENTITY_MISSING');
        }
        $session = $this->updatedIdentity->toSession();
        $session['must_change_password'] = false;
        $this->session->update($session);
        return $session;
    }

    private function registry(): OwasysRegistryModel
    {
        if (!$this->registry instanceof OwasysRegistryModel) {
            throw new RuntimeException('OWASYS_FSM_REGISTRY_HANDLER_UNAVAILABLE');
        }
        return $this->registry;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function actor(array $context): array
    {
        $identity = is_array($context['identity'] ?? null)
            ? $context['identity']
            : [];
        $subject = trim((string) ($identity['subject'] ?? $identity['id'] ?? ''));
        $roles = is_array($identity['roles'] ?? null)
            ? array_values(array_filter($identity['roles'], 'is_string'))
            : [];
        if ($subject === '' || $roles === []) {
            throw new RuntimeException('OWASYS_FSM_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => trim((string) ($identity['provider'] ?? 'owasys-sso')),
        ];
    }
}

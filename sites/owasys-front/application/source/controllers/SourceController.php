<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\Response;
use Opus\Http\UrlBuilder;
use Opus\Http\LocalizedRouteResolverInterface;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Security\Csrf\CsrfTokenManager;
use Opus\Security\Csrf\CsrfTokenManagerInterface;

/** Server-rendered OWASYS source and Git workspace backed by secured REST. */
final class OwasysSourceController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const MAX_CONTENT_BYTES = 1048576;
    private const SOURCE_CSRF_SCOPE = 'owasys.source.editor';
    private const GIT_CSRF_SCOPE = 'owasys.source.git';

    private readonly OwasysLocaleRegistry $locales;
    private readonly OwasysNavigationBuilder $navigation;
    private readonly CsrfTokenManagerInterface $csrf;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysScorePageRenderer $renderer,
        private readonly LocalizedRouteResolverInterface $localizedRoutes,
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly OwasysSourceModel $source,
        ?CsrfTokenManagerInterface $csrf = null
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($this->siteRoot, $security);
        $this->csrf = $csrf ?? new CsrfTokenManager();
    }

    public function matchesCurrentRequest(): bool
    {
        [, $route] = $this->resolveRequest();
        return $route === 'source' || str_starts_with($route, 'source/');
    }

    public function run(): void
    {
        $this->sessionRuntime->start();
        [$locale, $route] = $this->resolveRequest();
        if ($route !== 'source' && !str_starts_with($route, 'source/')) {
            throw new RuntimeException('OWASYS_SOURCE_ROUTE_MISMATCH');
        }
        $sourcePath = $route === 'source'
            ? ''
            : substr($route, strlen('source/'));
        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->redirect($locale, 'login');
            return;
        }
        $this->security->assertAllowed($identity, 'source', 'open');
        $currentApp = $this->session->currentApp();
        if (!is_array($currentApp)) {
            $this->redirect($locale, 'applications');
            return;
        }

        $fsmConfig = $this->fsmConfig();
        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $store = new FsmSessionStore(self::FSM_SESSION_KEY);
        $store->restore($fsm);
        $state = $this->enterSourceState(
            $fsm,
            $store,
            $locale,
            $sourcePath,
            $identity,
            $currentApp
        );
        if ($state === null) {
            return;
        }
        $this->applyProfilerSignal(
            $fsm,
            $store,
            $locale,
            $sourcePath,
            $identity,
            $currentApp
        );

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $listing = $this->emptyListing();
        $selected = null;
        $preview = null;
        $sourceErrorCode = null;
        $sourceFeedback = '';
        $submittedContent = null;
        $submittedHash = null;
        $gitStatus = null;
        $gitHistory = null;
        $gitDiff = null;
        $gitErrorCode = null;
        $gitFeedback = '';
        $gitSubmittedMessage = '';
        $sourceLoaded = false;
        $siteId = (string) ($currentApp['id'] ?? '');
        $gitPost = $method === 'POST'
            && array_key_exists('git_action', $_POST);

        if ($method === 'GET') {
            try {
                $sourceFeedback = $this->sourceStatus();
                $gitFeedback = $this->gitStatusOption();
                [$listing, $selected] = $this->loadSelection(
                    $siteId,
                    $sourcePath,
                    $identity
                );
                $sourceLoaded = true;
                if ($sourcePath !== '' && $this->expectsJson()) {
                    Response::json([
                        'contract' => 'OWASYS_SOURCE_SELECTION_V2',
                        'selected' => $selected,
                    ])->send();
                    return;
                }
            } catch (Throwable $error) {
                $sourceErrorCode = $this->safeErrorCode($error);
                http_response_code($this->statusForError($sourceErrorCode));
            }
        } elseif ($method === 'POST') {
            if ($gitPost) {
                try {
                    [$gitAction, $gitResult] = $this->handleGitMutation(
                        $fsm,
                        $store,
                        $siteId,
                        $sourcePath,
                        $locale,
                        $identity,
                        $currentApp
                    );
                    if ($this->expectsJson()) {
                        Response::json([
                            'contract' => 'OWASYS_GIT_ACTION_V1',
                            'action' => $gitAction,
                            'result' => $gitResult,
                        ])->send();
                        return;
                    }
                    $this->redirectCurrentSource(
                        $locale,
                        $sourcePath,
                        ['git_status' => $this->gitSuccessStatus($gitAction)]
                    );
                    return;
                } catch (Throwable $error) {
                    $gitErrorCode = $this->safeErrorCode($error);
                    $gitFeedback = 'failed';
                    $gitSubmittedMessage = is_string(
                        $_POST['commit_message'] ?? null
                    ) ? (string) $_POST['commit_message'] : '';
                    http_response_code($this->statusForError($gitErrorCode));
                    $this->recordGitFailure(
                        $fsm,
                        $store,
                        $identity,
                        $currentApp,
                        $locale,
                        $sourcePath
                    );
                    if ($this->expectsJson()) {
                        Response::json([
                            'contract' => 'OWASYS_GIT_ERROR_V1',
                            'error_code' => $gitErrorCode,
                        ], $this->statusForError($gitErrorCode))->send();
                        return;
                    }
                }
            } else {
                try {
                    if ($sourcePath === '') {
                        throw new RuntimeException(
                            'OWASYS_SOURCE_SELECTION_REQUIRED'
                        );
                    }
                    $this->csrf->assertValid(
                        self::SOURCE_CSRF_SCOPE,
                        $this->postedCsrfToken()
                    );
                    $action = $this->postedSourceAction();
                    $submittedContent = $this->postedContent();
                    $submittedHash = $this->postedHash();
                    $context = $this->sourceActionContext(
                        $identity,
                        $currentApp,
                        $locale,
                        $sourcePath,
                        $action
                    );

                    if ($action === 'preview') {
                        $this->security->assertAllowed(
                            $identity,
                            'source',
                            'preview'
                        );
                        $this->sourceTransition(
                            $fsm,
                            $store,
                            'preview_source',
                            $context
                        );
                        [$listing, $selected] = $this->loadSelection(
                            $siteId,
                            $sourcePath,
                            $identity
                        );
                        $sourceLoaded = true;
                        $preview = $this->source->preview(
                            $siteId,
                            $sourcePath,
                            $submittedHash,
                            $submittedContent,
                            $identity
                        );
                        $this->sourceTransition(
                            $fsm,
                            $store,
                            'source_previewed',
                            array_replace($context, [
                                'source_changed' =>
                                    ($preview['changed'] ?? false) === true,
                            ])
                        );
                        $sourceFeedback = 'previewed';
                        if ($this->expectsJson()) {
                            Response::json([
                                'contract' => 'OWASYS_SOURCE_PREVIEW_V1',
                                'preview' => $preview,
                            ])->send();
                            return;
                        }
                    } else {
                        $this->security->assertAllowed(
                            $identity,
                            'source',
                            'write'
                        );
                        $this->sourceTransition(
                            $fsm,
                            $store,
                            'write_source',
                            $context
                        );
                        $written = $this->source->write(
                            $siteId,
                            $sourcePath,
                            $submittedHash,
                            $submittedContent,
                            $identity
                        );
                        $this->sourceTransition(
                            $fsm,
                            $store,
                            'source_written',
                            array_replace($context, [
                                'source_changed' =>
                                    ($written['changed'] ?? false) === true,
                            ])
                        );
                        if ($this->expectsJson()) {
                            Response::json([
                                'contract' => 'OWASYS_SOURCE_WRITE_V1',
                                'written' => $written,
                            ])->send();
                            return;
                        }
                        $this->redirectSource(
                            $locale,
                            $sourcePath,
                            ['source_status' => 'saved']
                        );
                        return;
                    }
                } catch (Throwable $error) {
                    $sourceErrorCode = $this->safeErrorCode($error);
                    http_response_code(
                        $this->statusForError($sourceErrorCode)
                    );
                    $this->recordSourceFailure(
                        $fsm,
                        $store,
                        $identity,
                        $currentApp,
                        $locale,
                        $sourcePath,
                        $sourceErrorCode
                    );
                    $sourceFeedback = $sourceErrorCode
                        === 'OPUS_SITE_SOURCE_CONFLICT'
                            ? 'conflict'
                            : 'failed';
                    if ($this->expectsJson()) {
                        Response::json([
                            'contract' => 'OWASYS_SOURCE_ERROR_V1',
                            'error_code' => $sourceErrorCode,
                        ], $this->statusForError($sourceErrorCode))->send();
                        return;
                    }
                }
            }
        } else {
            $sourceErrorCode = 'OWASYS_SOURCE_METHOD_NOT_ALLOWED';
            http_response_code(405);
        }

        if (!$sourceLoaded) {
            try {
                [$listing, $selected] = $this->loadSelection(
                    $siteId,
                    $sourcePath,
                    $identity
                );
            } catch (Throwable $error) {
                if ($sourceErrorCode === null) {
                    $sourceErrorCode = $this->safeErrorCode($error);
                    http_response_code(
                        $this->statusForError($sourceErrorCode)
                    );
                }
            }
        }

        try {
            $this->security->assertAllowed($identity, 'git', 'read');
            $gitStatus = $this->source->gitStatus($siteId, $identity);
            $gitHistory = $this->source->gitHistory($siteId, $identity);
            if ($sourcePath !== '') {
                $gitDiff = $this->source->gitDiff(
                    $siteId,
                    $sourcePath,
                    $identity
                );
            }
        } catch (Throwable $error) {
            if ($gitErrorCode === null) {
                $gitErrorCode = $this->safeErrorCode($error);
                $gitFeedback = 'failed';
                if ($sourceErrorCode === null) {
                    http_response_code(
                        $this->statusForError($gitErrorCode)
                    );
                }
            }
        }

        $this->render(
            $fsmConfig,
            $state,
            $locale,
            $identity,
            $currentApp,
            $listing,
            $selected,
            $preview,
            $submittedContent,
            $submittedHash,
            $sourceFeedback,
            $sourceErrorCode,
            $gitStatus,
            $gitHistory,
            $gitDiff,
            $gitFeedback,
            $gitErrorCode,
            $gitSubmittedMessage
        );
    }

    /** @return array<string,mixed> */
    private function emptyListing(): array
    {
        return [
            'contract' => 'OPUS_SITE_SOURCE_LIST_V1',
            'files' => [],
            'truncated' => false,
        ];
    }

    /**
     * @param array<string,mixed> $identity
     * @return array{0:array<string,mixed>,1:array<string,mixed>|null}
     */
    private function loadSelection(
        string $siteId,
        string $sourcePath,
        array $identity
    ): array {
        if ($sourcePath === '') {
            return [$this->source->list($siteId, $identity), null];
        }
        $browse = $this->source->browse(
            $siteId,
            $sourcePath,
            $identity
        );
        if (!is_array($browse['listing'] ?? null)
            || !is_array($browse['selected'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_SOURCE_BROWSE_RESULT_INVALID'
            );
        }
        return [$browse['listing'], $browse['selected']];
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @return array{0:string,1:array<string,mixed>}
     */
    private function handleGitMutation(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $siteId,
        string $sourcePath,
        string $locale,
        array $identity,
        array $currentApp
    ): array {
        $this->csrf->assertValid(
            self::GIT_CSRF_SCOPE,
            $this->postedCsrfToken()
        );
        $action = $this->postedGitAction();
        $path = in_array($action, ['commit', 'stage_all'], true)
            ? ''
            : $this->postedGitPath();
        $permission = $action === 'stage_all' ? 'stage' : $action;
        $this->security->assertAllowed($identity, 'git', $permission);
        $context = $this->gitActionContext(
            $identity,
            $currentApp,
            $locale,
            $sourcePath,
            $action,
            $path
        );
        $this->sourceTransition(
            $fsm,
            $store,
            $this->gitRequestedSignal($action),
            $context
        );

        $result = match ($action) {
            'stage' => $this->source->gitStage(
                $siteId,
                $path,
                $identity
            ),
            'stage_all' => $this->source->gitStageAll(
                $siteId,
                $identity
            ),
            'unstage' => $this->source->gitUnstage(
                $siteId,
                $path,
                $identity
            ),
            'commit' => $this->source->gitCommit(
                $siteId,
                $this->postedCommitMessage(),
                $identity
            ),
            'restore' => $this->source->gitRestore(
                $siteId,
                $path,
                $this->postedGitHash(),
                $this->postedRestoreConfirmation(),
                $identity
            ),
            default => throw new RuntimeException(
                'OWASYS_GIT_ACTION_INVALID'
            ),
        };

        $this->sourceTransition(
            $fsm,
            $store,
            $this->gitCompletedSignal($action),
            $context
        );
        return [$action, $result];
    }

    private function postedCsrfToken(): string
    {
        $value = $_POST['csrf_token'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OPUS_CSRF_TOKEN_INVALID');
        }
        return $value;
    }

    private function postedSourceAction(): string
    {
        $value = $_POST['source_action'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_SOURCE_ACTION_INVALID');
        }
        $value = strtolower(trim($value));
        if (!in_array($value, ['preview', 'write'], true)) {
            throw new RuntimeException('OWASYS_SOURCE_ACTION_INVALID');
        }
        return $value;
    }

    private function postedGitAction(): string
    {
        $value = $_POST['git_action'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_GIT_ACTION_INVALID');
        }
        $value = strtolower(trim($value));
        if (!in_array(
            $value,
            ['stage', 'stage_all', 'unstage', 'commit', 'restore'],
            true
        )) {
            throw new RuntimeException('OWASYS_GIT_ACTION_INVALID');
        }
        return $value;
    }

    private function postedContent(): string
    {
        $value = $_POST['new_content'] ?? null;
        if (!is_string($value)
            || strlen($value) > self::MAX_CONTENT_BYTES
            || str_contains($value, "\0")) {
            throw new RuntimeException('OWASYS_SOURCE_CONTENT_INVALID');
        }
        return $value;
    }

    private function postedHash(): string
    {
        return $this->validatedHash(
            $_POST['expected_content_hash'] ?? null,
            'OWASYS_SOURCE_HASH_INVALID'
        );
    }

    private function postedGitHash(): string
    {
        return $this->validatedHash(
            $_POST['git_expected_content_hash'] ?? null,
            'OWASYS_GIT_RESTORE_HASH_INVALID'
        );
    }

    private function validatedHash(mixed $value, string $error): string
    {
        if (!is_string($value)) {
            throw new RuntimeException($error);
        }
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException($error);
        }
        return $value;
    }

    private function postedGitPath(): string
    {
        $value = $_POST['git_path'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_GIT_PATH_INVALID');
        }
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === ''
            || str_contains($value, '..')
            || str_contains($value, "\0")
            || preg_match('/^[A-Za-z0-9._\/-]{1,512}$/D', $value) !== 1) {
            throw new RuntimeException('OWASYS_GIT_PATH_INVALID');
        }
        return $value;
    }

    private function postedCommitMessage(): string
    {
        $value = $_POST['commit_message'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException(
                'OWASYS_GIT_COMMIT_MESSAGE_INVALID'
            );
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RuntimeException(
                'OWASYS_GIT_COMMIT_MESSAGE_INVALID'
            );
        }
        return $value;
    }

    private function postedRestoreConfirmation(): string
    {
        $value = $_POST['restore_confirmation'] ?? null;
        if (!is_string($value)
            || strlen($value) > 700
            || str_contains($value, "\0")) {
            throw new RuntimeException(
                'OWASYS_GIT_RESTORE_CONFIRMATION_INVALID'
            );
        }
        return $value;
    }

    private function sourceStatus(): string
    {
        $value = $_GET['source_status'] ?? '';
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_SOURCE_STATUS_INVALID');
        }
        $value = strtolower(trim($value));
        if (!in_array($value, ['', 'saved'], true)) {
            throw new RuntimeException('OWASYS_SOURCE_STATUS_INVALID');
        }
        return $value;
    }

    private function gitStatusOption(): string
    {
        $value = $_GET['git_status'] ?? '';
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_GIT_STATUS_OPTION_INVALID');
        }
        $value = strtolower(trim($value));
        if (!in_array(
            $value,
            ['', 'staged', 'staged_all', 'unstaged', 'committed', 'restored'],
            true
        )) {
            throw new RuntimeException('OWASYS_GIT_STATUS_OPTION_INVALID');
        }
        return $value;
    }

    private function gitSuccessStatus(string $action): string
    {
        return match ($action) {
            'stage' => 'staged',
            'stage_all' => 'staged_all',
            'unstage' => 'unstaged',
            'commit' => 'committed',
            'restore' => 'restored',
            default => throw new RuntimeException(
                'OWASYS_GIT_ACTION_INVALID'
            ),
        };
    }

    private function gitRequestedSignal(string $action): string
    {
        return match ($action) {
            'stage', 'stage_all' => 'stage_source',
            'unstage' => 'unstage_source',
            'commit' => 'commit_source',
            'restore' => 'restore_source',
            default => throw new RuntimeException(
                'OWASYS_GIT_ACTION_INVALID'
            ),
        };
    }

    private function gitCompletedSignal(string $action): string
    {
        return match ($action) {
            'stage', 'stage_all' => 'source_staged',
            'unstage' => 'source_unstaged',
            'commit' => 'source_committed',
            'restore' => 'source_restored',
            default => throw new RuntimeException(
                'OWASYS_GIT_ACTION_INVALID'
            ),
        };
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @return array<string,mixed>
     */
    private function sourceActionContext(
        array $identity,
        array $currentApp,
        string $locale,
        string $sourcePath,
        string $action
    ): array {
        return [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
            'source_action' => strtolower(trim($action)),
        ];
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @return array<string,mixed>
     */
    private function gitActionContext(
        array $identity,
        array $currentApp,
        string $locale,
        string $sourcePath,
        string $action,
        string $gitPath
    ): array {
        return [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
            'git_action' => strtolower(trim($action)),
            'git_path' => trim(str_replace('\\', '/', $gitPath), '/'),
        ];
    }

    /** @param array<string,mixed> $context */
    private function sourceTransition(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $signal,
        array $context
    ): void {
        $transition = $fsm->transition('source', $signal, $context);
        if (($transition['next_state'] ?? null) !== 'source') {
            throw new RuntimeException(
                'OWASYS_SOURCE_FSM_STATE_INVALID'
            );
        }
        $store->persist($fsm);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function recordGitFailure(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        array $identity,
        array $currentApp,
        string $locale,
        string $sourcePath
    ): void {
        try {
            $this->sourceTransition(
                $fsm,
                $store,
                'git_action_failed',
                $this->gitActionContext(
                    $identity,
                    $currentApp,
                    $locale,
                    $sourcePath,
                    is_string($_POST['git_action'] ?? null)
                        ? (string) $_POST['git_action']
                        : '',
                    is_string($_POST['git_path'] ?? null)
                        ? (string) $_POST['git_path']
                        : ''
                )
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function recordSourceFailure(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        array $identity,
        array $currentApp,
        string $locale,
        string $sourcePath,
        string $errorCode
    ): void {
        try {
            $signal = $errorCode === 'OPUS_SITE_SOURCE_CONFLICT'
                ? 'source_conflict'
                : 'source_action_failed';
            $this->sourceTransition(
                $fsm,
                $store,
                $signal,
                $this->sourceActionContext(
                    $identity,
                    $currentApp,
                    $locale,
                    $sourcePath,
                    is_string($_POST['source_action'] ?? null)
                        ? (string) $_POST['source_action']
                        : ''
                )
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function enterSourceState(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $locale,
        string $sourcePath,
        array $identity,
        array $currentApp
    ): ?string {
        $current = $fsm->currentState();
        $context = [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
        ];
        if ($current !== 'source') {
            $transition = $fsm->transition($current, 'open_source', $context);
            $current = (string) ($transition['next_state'] ?? '');
            if ($current !== 'source') {
                throw new RuntimeException(
                    'OWASYS_SOURCE_FSM_STATE_INVALID'
                );
            }
        }
        $previousLocale = array_key_exists('locale', $fsm->memory())
            ? (string) $fsm->peek('locale')
            : '';
        $rememberedPath = array_key_exists('source_path', $fsm->memory())
            ? (string) $fsm->peek('source_path')
            : '';
        if ($sourcePath === '' && $previousLocale !== ''
            && $previousLocale !== $locale && $rememberedPath !== '') {
            $fsm->transition('source', 'change_locale', $context);
            $store->persist($fsm);
            $this->redirect($locale, $this->sourceRoute($rememberedPath));
            return null;
        }
        $fsm->transition('source', 'open_source_file', $context);
        $store->persist($fsm);
        return $current;
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     * @param array<string,mixed> $listing
     * @param array<string,mixed>|null $selected
     * @param array<string,mixed>|null $preview
     * @param array<string,mixed>|null $gitStatus
     * @param array<string,mixed>|null $gitHistory
     * @param array<string,mixed>|null $gitDiff
     */
    private function render(
        array $fsmConfig,
        string $state,
        string $locale,
        array $identity,
        array $currentApp,
        array $listing,
        ?array $selected,
        ?array $preview,
        ?string $submittedContent,
        ?string $submittedHash,
        string $sourceFeedback,
        ?string $sourceErrorCode,
        ?array $gitStatus,
        ?array $gitHistory,
        ?array $gitDiff,
        string $gitFeedback,
        ?string $gitErrorCode,
        string $gitSubmittedMessage
    ): void {
        $basePath = $this->basePath();
        $routeUrl = fn (string $target): string => $this->routeUrl(
            $locale,
            $target
        );
        $selectedPath = (string) ($selected['path'] ?? '');
        $selectedUrl = $selectedPath === ''
            ? $this->routeUrl($locale, 'source')
            : $this->sourceUrl($locale, $selectedPath);
        $files = [];
        foreach ((array) ($listing['files'] ?? []) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $path = (string) ($file['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $files[] = [
                'path' => $path,
                'name' => basename($path),
                'bytes' => (string) ($file['bytes'] ?? '0'),
                'selected' => $path === $selectedPath,
                'url' => $this->sourceUrl($locale, $path),
            ];
        }

        $conflict = $sourceErrorCode === 'OPUS_SITE_SOURCE_CONFLICT';
        $selectedPresent = is_array($selected);
        $roleCanPreview = $this->security->isAllowed($identity, 'source', 'preview');
        $roleCanWrite = $this->security->isAllowed($identity, 'source', 'write');
        $editable = $selectedPresent && $roleCanWrite;
        $canPreview = $selectedPresent && $roleCanPreview && !$conflict;
        $canWrite = $selectedPresent && $roleCanWrite && !$conflict;
        $serverHash = strtolower(trim((string) ($selected['sha256'] ?? '')));
        $expectedHash = is_string($submittedHash)
            && preg_match('/^[a-f0-9]{64}$/D', $submittedHash) === 1
                ? $submittedHash
                : $serverHash;
        $serverContent = (string) ($selected['content'] ?? '');
        $content = $submittedContent ?? $serverContent;
        $initialDirty = $selectedPresent && $content !== $serverContent;

        $sourcePaths = array_fill_keys(
            array_map(
                static fn (array $file): string => (string) $file['path'],
                $files
            ),
            true
        );
        $gitAvailable = is_array($gitStatus) && is_array($gitHistory);
        $gitCanStage = $this->security->isAllowed($identity, 'git', 'stage');
        $gitCanUnstage = $this->security->isAllowed($identity, 'git', 'unstage');
        $gitCanCommit = $this->security->isAllowed($identity, 'git', 'commit');
        $gitCanRestore = $this->security->isAllowed($identity, 'git', 'restore');
        $gitCsrfToken = $this->csrf->issue(self::GIT_CSRF_SCOPE);
        $gitChanges = [];
        $gitHasConflicts = false;
        foreach ((array) ($gitStatus['changes'] ?? []) as $change) {
            if (!is_array($change)) {
                continue;
            }
            $path = trim((string) ($change['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $sha256 = strtolower(trim((string) ($change['sha256'] ?? '')));
            $exists = ($change['exists'] ?? false) === true;
            $staged = ($change['staged'] ?? false) === true;
            $unstaged = ($change['unstaged'] ?? false) === true;
            $untracked = ($change['untracked'] ?? false) === true;
            $conflicted = ($change['conflicted'] ?? false) === true;
            $gitHasConflicts = $gitHasConflicts || $conflicted;
            $restoreAllowed = $gitCanRestore
                && $exists
                && $unstaged
                && !$untracked
                && preg_match('/^[a-f0-9]{64}$/D', $sha256) === 1;
            $gitChanges[] = [
                'path' => $path,
                'name' => basename($path),
                'source_readable' => isset($sourcePaths[$path]),
                'url' => isset($sourcePaths[$path])
                    ? $this->sourceUrl($locale, $path)
                    : '',
                'selected' => $path === $selectedPath,
                'index_status' => (string) ($change['index_status'] ?? ''),
                'worktree_status' => (string) (
                    $change['worktree_status'] ?? ''
                ),
                'staged' => $staged,
                'unstaged' => $unstaged,
                'untracked' => $untracked,
                'conflicted' => $conflicted,
                'can_stage' => $gitCanStage && ($unstaged || $untracked),
                'can_unstage' => $gitCanUnstage && $staged,
                'can_restore' => $restoreAllowed,
                'sha256' => $sha256,
                'restore_confirmation' => $restoreAllowed
                    ? 'RESTORE:' . (string) ($currentApp['id'] ?? '')
                        . ':' . $path . ':' . $sha256
                    : '',
            ];
        }
        $counts = is_array($gitStatus['counts'] ?? null)
            ? $gitStatus['counts']
            : [];
        $stagedCount = (int) ($counts['staged'] ?? 0);
        $stageableCount = (int) ($counts['unstaged'] ?? 0)
            + (int) ($counts['untracked'] ?? 0);
        $gitCommits = [];
        foreach ((array) ($gitHistory['commits'] ?? []) as $commit) {
            if (!is_array($commit)) {
                continue;
            }
            $gitCommits[] = [
                'hash' => (string) ($commit['hash'] ?? ''),
                'short_hash' => (string) ($commit['short_hash'] ?? ''),
                'author' => (string) ($commit['author'] ?? ''),
                'date' => (string) ($commit['date'] ?? ''),
                'subject' => (string) ($commit['subject'] ?? ''),
            ];
        }

        $data = [
            'page' => ['title' => '', 'summary' => ''],
            'fsm' => ['state' => $state, 'module' => 'source'],
            'identity' => [
                'authenticated' => true,
                'label' => (string) ($identity['label'] ?? ''),
                'primary_role' => (string) (
                    $identity['profile'] ?? $identity['roles'][0] ?? ''
                ),
            ],
            'current_app' => [
                'present' => true,
                'id' => (string) ($currentApp['id'] ?? ''),
                'name' => (string) (
                    $currentApp['name'] ?? $currentApp['id'] ?? ''
                ),
                'kind' => (string) ($currentApp['kind'] ?? ''),
                'root' => (string) ($currentApp['root_path'] ?? ''),
            ],
            'locale' => [
                'code' => $locale,
                'name' => $this->locales->name($locale),
                'flag' => $basePath . '/asset/flags/'
                    . rawurlencode($this->locales->flagCode($locale)) . '.svg',
            ],
            'locales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'name' => $this->locales->name($code),
                    'flag' => $basePath . '/asset/flags/'
                        . rawurlencode($this->locales->flagCode($code)) . '.svg',
                    'url' => $this->routeUrl(
                        $code,
                        $selectedPath === ''
                            ? 'source'
                            : $this->sourceRoute($selectedPath)
                    ),
                    'active' => $code === $locale,
                ],
                $this->locales->codes()
            ),
            'assets' => [
                'score_css' => $basePath . '/asset/css/owasys.css',
                'theme_css' => $basePath
                    . '/asset/themes/owasys/css/theme.css?v=p117q',
                'language_css' => $basePath
                    . '/asset/css/language-switcher.css',
                'source_editor_css' => $basePath
                    . '/asset/css/source-editor.css?v=p117w-e3b',
                'password_js' => $basePath
                    . '/asset/js/password-visibility.js',
                'source_codemirror_js' => $basePath
                    . '/asset/vendor/codemirror/owasys-codemirror.js',
                'source_browser_js' => $basePath
                    . '/asset/js/source-browser.js?v=p117w-e2b',
            ],
            'urls' => [
                'home' => $this->routeUrl($locale, 'applications'),
                'login' => $this->routeUrl($locale, 'login'),
                'logout' => $this->routeUrl($locale, 'logout'),
                'account' => $this->routeUrl($locale, 'account/password'),
                'applications' => $this->routeUrl($locale, 'applications'),
                'current' => $this->routeUrl($locale, 'source'),
            ],
            'navigation' => $this->navigation->build(
                $fsmConfig,
                $identity,
                $state,
                true,
                $routeUrl
            ),
            'source' => [
                'browser_enabled' => true,
                'empty' => $files === [],
                'files' => $files,
                'truncated' => ($listing['truncated'] ?? false) === true,
                'selected' => $selectedPresent,
                'unselected' => !$selectedPresent,
                'selection_class' => $selectedPresent ? '' : 'is-hidden',
                'unselected_class' => $selectedPresent ? 'is-hidden' : '',
                'path' => $selectedPath,
                'name' => $selectedPath === '' ? '' : basename($selectedPath),
                'url' => $selectedUrl,
                'form_action' => $selectedUrl,
                'reload_url' => $selectedUrl,
                'bytes' => (string) ($selected['bytes'] ?? ''),
                'sha256' => $serverHash,
                'expected_sha256' => $expectedHash,
                'csrf_token' => $this->csrf->issue(
                    self::SOURCE_CSRF_SCOPE
                ),
                'content' => $content,
                'server_content' => $serverContent,
                'editable' => $editable,
                'editable_value' => $roleCanWrite ? '1' : '0',
                'read_only' => !$roleCanWrite,
                'can_preview' => $canPreview,
                'cannot_preview' => !$canPreview,
                'can_write' => $canWrite,
                'cannot_write' => !$canWrite,
                'has_error' => $sourceErrorCode !== null,
                'error_code' => $sourceErrorCode ?? '',
                'saved' => $sourceFeedback === 'saved',
                'previewed' => $sourceFeedback === 'previewed',
                'conflict' => $sourceFeedback === 'conflict',
                'failed' => $sourceFeedback === 'failed',
                'clean' => !$initialDirty,
                'dirty' => $initialDirty,
            ],
            'source_preview' => [
                'present' => is_array($preview),
                'absent' => !is_array($preview),
                'changed' => ($preview['changed'] ?? false) === true,
                'unchanged' => is_array($preview)
                    && ($preview['changed'] ?? false) !== true,
                'current_sha256' => (string) (
                    $preview['current_sha256'] ?? ''
                ),
                'proposed_sha256' => (string) (
                    $preview['proposed_sha256'] ?? ''
                ),
                'current_bytes' => (string) (
                    $preview['current_bytes'] ?? ''
                ),
                'proposed_bytes' => (string) (
                    $preview['proposed_bytes'] ?? ''
                ),
                'diff' => (string) ($preview['diff'] ?? ''),
                'diff_truncated' =>
                    ($preview['diff_truncated'] ?? false) === true,
            ],
            'git' => [
                'available' => $gitAvailable,
                'unavailable' => !$gitAvailable,
                'failed' => $gitFeedback === 'failed',
                'error_code' => $gitErrorCode ?? '',
                'staged_success' => $gitFeedback === 'staged',
                'staged_all_success' => $gitFeedback === 'staged_all',
                'unstaged_success' => $gitFeedback === 'unstaged',
                'committed_success' => $gitFeedback === 'committed',
                'restored_success' => $gitFeedback === 'restored',
                'branch' => (string) ($gitStatus['branch'] ?? ''),
                'head' => (string) ($gitStatus['head'] ?? ''),
                'short_head' => substr(
                    (string) ($gitStatus['head'] ?? ''),
                    0,
                    12
                ),
                'clean' => ($gitStatus['clean'] ?? false) === true,
                'dirty' => is_array($gitStatus)
                    && ($gitStatus['clean'] ?? false) !== true,
                'changes' => $gitChanges,
                'changes_empty' => $gitChanges === [],
                'counts' => [
                    'total' => (string) ($counts['total'] ?? '0'),
                    'staged' => (string) ($counts['staged'] ?? '0'),
                    'unstaged' => (string) ($counts['unstaged'] ?? '0'),
                    'untracked' => (string) ($counts['untracked'] ?? '0'),
                ],
                'commits' => $gitCommits,
                'history_empty' => $gitCommits === [],
                'csrf_token' => $gitCsrfToken,
                'form_action' => $selectedUrl,
                'can_stage_all' => $gitCanStage
                    && !$gitHasConflicts
                    && $stageableCount > 0,
                'can_commit' => $gitCanCommit && $stagedCount > 0,
                'cannot_commit' => !$gitCanCommit || $stagedCount < 1,
                'read_only' => !(
                    $gitCanStage
                    || $gitCanUnstage
                    || $gitCanCommit
                    || $gitCanRestore
                ),
                'commit_message' => $gitSubmittedMessage,
            ],
            'git_diff' => [
                'present' => is_array($gitDiff),
                'absent' => !is_array($gitDiff),
                'path' => (string) ($gitDiff['path'] ?? ''),
                'unstaged' => (string) ($gitDiff['unstaged'] ?? ''),
                'staged' => (string) ($gitDiff['staged'] ?? ''),
                'has_unstaged' => trim((string) (
                    $gitDiff['unstaged'] ?? ''
                )) !== '',
                'has_staged' => trim((string) (
                    $gitDiff['staged'] ?? ''
                )) !== '',
                'empty' => is_array($gitDiff)
                    && trim((string) ($gitDiff['unstaged'] ?? '')) === ''
                    && trim((string) ($gitDiff['staged'] ?? '')) === '',
                'unstaged_truncated' =>
                    ($gitDiff['unstaged_truncated'] ?? false) === true,
                'staged_truncated' =>
                    ($gitDiff['staged_truncated'] ?? false) === true,
            ],
        ];

        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('source/templates/index.score', $data);
    }


    /** @return array{0:string,1:string} */
    private function resolveRequest(): array
    {
        $path = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        $path = is_string($path) ? rawurldecode($path) : '/';
        $segments = trim($path, '/') === ''
            ? []
            : explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'owasys') {
            array_shift($segments);
        }
        $default = (string) ($this->siteConfig['default_locale'] ?? 'fr-FR');
        $negotiator = BrowserLocaleNegotiator::forLocales(
            $this->locales->codes(),
            $default
        );
        $first = (string) ($segments[0] ?? '');
        $explicit = $this->locales->resolveExplicit($first);
        if (is_string($explicit)) {
            $locale = $explicit;
            array_shift($segments);
        } else {
            $locale = $negotiator->negotiate(
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                    ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                    : null
            )->value;
        }
        $publicRoute = implode('/', $segments);
        return [
            $locale,
            $publicRoute === ''
                ? ''
                : $this->localizedRoutes->resolve(
                    $locale,
                    $publicRoute
                ),
        ];
    }

    /** @return array<string,mixed> */
    private function fsmConfig(): array
    {
        $navigation = is_array($this->siteConfig['navigation'] ?? null)
            ? $this->siteConfig['navigation']
            : [];
        $relative = trim(str_replace(
            '\\',
            '/',
            (string) ($navigation['fsm'] ?? '')
        ), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('OWASYS_SOURCE_FSM_PATH_INVALID');
        }
        return StructuredFileLoader::instance()->read(
            $this->siteRoot . '/' . $relative
        );
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OWASYS_SOURCE_FAILED';
    }

    private function statusForError(string $errorCode): int
    {
        return match (true) {
            str_contains($errorCode, 'CONFLICT') => 409,
            str_contains($errorCode, 'ACL') => 403,
            str_contains($errorCode, 'CSRF') => 403,
            str_contains($errorCode, 'METHOD') => 405,
            str_contains($errorCode, 'NOTHING_STAGED') => 409,
            str_contains($errorCode, 'FOREIGN_STAGE') => 409,
            default => 422,
        };
    }

    private function expectsJson(): bool
    {
        return str_contains(
            strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
            'application/json'
        );
    }

    private function routeUrl(string $locale, string $route): string
    {
        return $this->localizedRoutes->url(
            $this->basePath(),
            $locale,
            $route
        );
    }

    private function sourceUrl(string $locale, string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        if ($segments === []) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return $this->localizedRoutes->url(
            $this->basePath(),
            $locale,
            'source/' . implode('/', array_values($segments))
        );
    }

    private function sourceRoute(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        if ($segments === []) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return 'source/' . implode('/', $segments);
    }

    /**
     * @param array<string,mixed> $identity
     * @param array<string,mixed> $currentApp
     */
    private function applyProfilerSignal(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $locale,
        string $sourcePath,
        array $identity,
        array $currentApp
    ): void {
        $raw = $_GET['profiler'] ?? null;
        if ($raw !== null && (string) $raw !== '1') {
            throw new RuntimeException('OWASYS_PROFILER_OPTION_INVALID');
        }
        $isOpen = array_key_exists('profiler_open', $fsm->memory())
            && $fsm->peek('profiler_open') === true;
        $context = [
            'identity' => $identity,
            'is_authenticated' => true,
            'roles' => is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : [],
            'current_app' => $currentApp,
            'has_current_app' => true,
            'locale' => $locale,
            'source_path' => $sourcePath,
            'return_url' => $this->routeUrl(
                $locale,
                $sourcePath === ''
                    ? 'source'
                    : $this->sourceRoute($sourcePath)
            ),
            'trace_id' => trim((string) getenv('OPUS_TRACE_ID')),
            'profiler_open' => true,
            'profiler_closed' => false,
        ];
        if ((string) $raw === '1' && !$isOpen) {
            $this->security->assertAllowed($identity, 'profiler', 'view');
            $fsm->transition('source', 'open_profiler', $context);
            $store->persist($fsm);
        } elseif ($raw === null && $isOpen) {
            $context['profiler_open'] = false;
            $context['profiler_closed'] = true;
            $fsm->transition('source', 'close_profiler', $context);
            $store->persist($fsm);
        }
    }

    private function basePath(): string
    {
        $script = str_replace('\\', '/', (string) (
            $_SERVER['SCRIPT_NAME'] ?? ''
        ));
        $directory = str_replace('\\', '/', dirname($script));
        return in_array($directory, ['/', '.', ''], true)
            ? ''
            : rtrim($directory, '/');
    }

    /** @param array<string,scalar|null> $query */
    private function redirectSource(
        string $locale,
        string $path,
        array $query
    ): void {
        $url = (new UrlBuilder())->withQuery(
            $this->sourceUrl($locale, $path),
            $query
        );
        Response::empty(303, ['Location' => $url])->send();
    }

    /** @param array<string,scalar|null> $query */
    private function redirectCurrentSource(
        string $locale,
        string $path,
        array $query
    ): void {
        $target = $path === ''
            ? $this->routeUrl($locale, 'source')
            : $this->sourceUrl($locale, $path);
        $url = (new UrlBuilder())->withQuery($target, $query);
        Response::empty(303, ['Location' => $url])->send();
    }

    private function redirect(string $locale, string $route): void
    {
        Response::empty(303, [
            'Location' => $this->routeUrl($locale, $route),
        ])->send();
    }
}

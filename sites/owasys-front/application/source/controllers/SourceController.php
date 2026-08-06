<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\Response;
use Opus\Http\UrlBuilder;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Security\Csrf\CsrfTokenManager;
use Opus\Security\Csrf\CsrfTokenManagerInterface;

/** Server-rendered OWASYS source editor backed exclusively by secured REST. */
final class OwasysSourceController
{
    private const FSM_SESSION_KEY = 'opus.fsm.owasys-front';
    private const MAX_CONTENT_BYTES = 1048576;
    private const CSRF_SCOPE = 'owasys.source.editor';

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
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly OwasysSourceModel $source,
        ?CsrfTokenManagerInterface $csrf = null
    ) {
        $this->locales = new OwasysLocaleRegistry($siteConfig);
        $this->navigation = new OwasysNavigationBuilder($security);
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
        }
        $this->security->assertAllowed($identity, 'source', 'open');
        $currentApp = $this->session->currentApp();
        if (!is_array($currentApp)) {
            $this->redirect($locale, 'applications');
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
        $errorCode = null;
        $feedback = '';
        $submittedContent = null;
        $submittedHash = null;
        $siteId = (string) ($currentApp['id'] ?? '');

        try {
            if ($method === 'GET') {
                $feedback = $this->sourceStatus();
                [$listing, $selected] = $this->loadSelection(
                    $siteId,
                    $sourcePath,
                    $identity
                );
                if ($sourcePath !== '' && $this->expectsJson()) {
                    Response::json([
                        'contract' => 'OWASYS_SOURCE_SELECTION_V2',
                        'selected' => $selected,
                    ])->send();
                    return;
                }
            } elseif ($method === 'POST') {
                if ($sourcePath === '') {
                    throw new RuntimeException(
                        'OWASYS_SOURCE_SELECTION_REQUIRED'
                    );
                }
                $this->csrf->assertValid(
                    self::CSRF_SCOPE,
                    $this->postedCsrfToken()
                );
                $action = $this->postedAction();
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
                    $feedback = 'previewed';
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
                }
            } else {
                throw new RuntimeException(
                    'OWASYS_SOURCE_METHOD_NOT_ALLOWED'
                );
            }
        } catch (Throwable $error) {
            $errorCode = $this->safeErrorCode($error);
            http_response_code($this->statusForError($errorCode));
            if ($method === 'POST') {
                $signal = $errorCode === 'OPUS_SITE_SOURCE_CONFLICT'
                    ? 'source_conflict'
                    : 'source_action_failed';
                try {
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
                try {
                    [$listing, $selected] = $this->loadSelection(
                        $siteId,
                        $sourcePath,
                        $identity
                    );
                } catch (Throwable) {
                    $listing = $this->emptyListing();
                    $selected = null;
                }
                $feedback = $errorCode === 'OPUS_SITE_SOURCE_CONFLICT'
                    ? 'conflict'
                    : 'failed';
            }
            if ($this->expectsJson()) {
                Response::json([
                    'contract' => 'OWASYS_SOURCE_ERROR_V1',
                    'error_code' => $errorCode,
                ], $this->statusForError($errorCode))->send();
                return;
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
            $feedback,
            $errorCode
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

    private function postedCsrfToken(): string
    {
        $value = $_POST['csrf_token'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OPUS_CSRF_TOKEN_INVALID');
        }
        return $value;
    }

    private function postedAction(): string
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
        $value = $_POST['expected_content_hash'] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('OWASYS_SOURCE_HASH_INVALID');
        }
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_HASH_INVALID');
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
    private function enterSourceState(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        string $locale,
        string $sourcePath,
        array $identity,
        array $currentApp
    ): string {
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
        string $feedback,
        ?string $errorCode
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

        $conflict = $errorCode === 'OPUS_SITE_SOURCE_CONFLICT';
        $selectedPresent = is_array($selected);
        $roleCanPreview = $this->isAllowed($identity, 'preview');
        $roleCanWrite = $this->isAllowed($identity, 'write');
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

        $data = [
            'page' => ['title' => '', 'summary' => ''],
            'fsm' => ['state' => $state, 'module' => 'source'],
            'identity' => [
                'authenticated' => true,
                'label' => (string) ($identity['label'] ?? ''),
                'primary_role' => (string) (
                    $identity['roles'][0] ?? $identity['profile'] ?? ''
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
                    . '/asset/css/source-editor.css?v=p117w-e2b',
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
                'csrf_token' => $this->csrf->issue(self::CSRF_SCOPE),
                'content' => $content,
                'server_content' => $serverContent,
                'editable' => $editable,
                'editable_value' => $roleCanWrite ? '1' : '0',
                'read_only' => !$editable,
                'can_preview' => $canPreview,
                'cannot_preview' => !$canPreview,
                'can_write' => $canWrite,
                'cannot_write' => !$canWrite,
                'has_error' => $errorCode !== null,
                'error_code' => $errorCode ?? '',
                'saved' => $feedback === 'saved',
                'previewed' => $feedback === 'previewed',
                'conflict' => $feedback === 'conflict',
                'failed' => $feedback === 'failed',
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
        ];

        header('Content-Type: text/html; charset=UTF-8');
        $this->renderer->emit('source/templates/index.score', $data);
    }

    /** @param array<string,mixed> $identity */
    private function isAllowed(array $identity, string $action): bool
    {
        try {
            $this->security->assertAllowed($identity, 'source', $action);
            return true;
        } catch (Throwable) {
            return false;
        }
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
        return [$locale, implode('/', $segments)];
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
            $errorCode === 'OPUS_SITE_SOURCE_CONFLICT' => 409,
            str_contains($errorCode, 'ACL') => 403,
            str_contains($errorCode, 'CSRF') => 403,
            str_contains($errorCode, 'METHOD') => 405,
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
        $segments = array_values(array_filter(
            explode('/', trim($route, '/')),
            'strlen'
        ));
        array_unshift($segments, $locale);
        return (new UrlBuilder($this->basePath()))->build($segments);
    }

    private function sourceUrl(string $locale, string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        if ($segments === []) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return (new UrlBuilder($this->basePath()))->build(
            array_merge([$locale, 'source'], array_values($segments))
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
    ): never {
        $url = (new UrlBuilder())->withQuery(
            $this->sourceUrl($locale, $path),
            $query
        );
        header('Location: ' . $url, true, 303);
        exit;
    }

    private function redirect(string $locale, string $route): never
    {
        header('Location: ' . $this->routeUrl($locale, $route), true, 303);
        exit;
    }
}

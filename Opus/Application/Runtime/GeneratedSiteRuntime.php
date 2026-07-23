<?php
declare(strict_types=1);

namespace Opus\Application\Runtime;

use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmSiteLoader;
use Opus\Http\Response;
use Opus\I18n\ApplicationTranslationRuntime;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Template\ScoreTemplateRenderer;

/**
 * Generic FSM-module-first runtime used by applications generated through Composer.
 *
 * Configuration crosses File + StructuredFileLoader, navigation crosses FSM,
 * access is deny-by-default, identity crosses session/Auth0 proxy SSO, browser
 * locale is negotiated by OPUS I18n and every visible document is SCORE-rendered.
 */
final class GeneratedSiteRuntime implements GeneratedSiteRuntimeInterface
{
    private readonly string $siteRoot;
    private readonly StructuredFileLoader $loader;
    private readonly File $file;

    public function __construct(string $siteRoot)
    {
        $root = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_GENERATED_SITE_ROOT_INVALID');
        }
        $this->siteRoot = $root;
        $this->loader = StructuredFileLoader::instance();
        $this->file = File::instance();
    }

    public function handle(): Response
    {
        try {
            $site = $this->config('config/site.json', 'OPUS_SITE_STANDARD_CONTRACT_CORE');
            $routes = $this->config('config/routes.json', 'OPUS_ROUTE_REGISTRY_V1');
            $acl = $this->config('config/acl.json', 'OPUS_GENERATED_APPLICATION_ACL_V1');
            $sso = $this->config('config/sso.json', 'OPUS_GENERATED_APPLICATION_SSO_V1');
            $this->startSession($sso);

            [$locale, $routePath] = $this->requestPath($site);
            $route = $this->matchRoute($routes, $routePath);
            $identity = $this->identity($sso);
            $this->assertAllowed($acl, (string) ($route['acl'] ?? 'public'), $identity);
            $state = $this->transition($site, $route, $identity);

            return Response::html($this->renderPage($site, $routes, $route, $state, $locale, $identity));
        } catch (\Throwable $error) {
            $code = $this->safeErrorCode($error);
            $status = match (true) {
                str_contains($code, 'AUTH_REQUIRED') => 401,
                str_contains($code, 'ACL_DENIED') => 403,
                str_contains($code, 'ROUTE_NOT_FOUND') => 404,
                default => 500,
            };
            return Response::html($this->renderError($code), $status);
        }
    }

    /** @return array<string,mixed> */
    private function config(string $relative, string $contract): array
    {
        $data = $this->loader->read($this->siteRoot . '/' . $relative);
        if (($data['contract'] ?? null) !== $contract) {
            throw new \RuntimeException('OPUS_GENERATED_CONFIG_CONTRACT_INVALID:' . $relative);
        }
        return $data;
    }

    /** @param array<string,mixed> $sso */
    private function startSession(array $sso): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $name = trim((string) ($sso['session_name'] ?? 'OPUS_GENERATED_APPLICATION'));
        if (preg_match('/^[A-Za-z0-9_-]{3,128}$/', $name) !== 1) {
            throw new \RuntimeException('OPUS_GENERATED_SESSION_NAME_INVALID');
        }
        session_name($name);
        session_start();
    }

    /** @param array<string,mixed> $site @return array{0:string,1:string} */
    private function requestPath(array $site): array
    {
        $supported = is_array($site['locales'] ?? null)
            ? array_values(array_filter($site['locales'], 'is_string'))
            : [];
        $default = trim((string) ($site['default_locale'] ?? ''));
        $negotiator = BrowserLocaleNegotiator::forLocales($supported, $default);

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $segments = trim($path, '/') === '' ? [] : explode('/', trim($path, '/'));
        $explicit = $negotiator->match((string) ($segments[0] ?? ''));
        if ($explicit !== null) {
            $locale = $explicit->value;
            array_shift($segments);
        } else {
            $locale = $negotiator->negotiate(
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                    ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                    : null
            )->value;
        }
        $routePath = '/' . trim(implode('/', $segments), '/');
        return [$locale, $routePath === '/' ? '/' : rtrim($routePath, '/')];
    }

    /** @param array<string,mixed> $routes @return array<string,mixed> */
    private function matchRoute(array $routes, string $path): array
    {
        foreach ((array) ($routes['routes'] ?? []) as $route) {
            if (is_array($route) && (string) ($route['path'] ?? '') === $path) {
                return $route;
            }
        }
        throw new \RuntimeException('OPUS_GENERATED_ROUTE_NOT_FOUND');
    }

    /** @param array<string,mixed> $sso @return array{subject:string,roles:list<string>,provider:string} */
    private function identity(array $sso): array
    {
        $key = trim((string) ($sso['session_identity_key'] ?? 'opus_identity'));
        $session = $_SESSION[$key] ?? null;
        if (is_array($session)) {
            $subject = trim((string) ($session['subject'] ?? $session['id'] ?? ''));
            $roles = is_array($session['roles'] ?? null)
                ? array_values(array_filter($session['roles'], 'is_string'))
                : [];
            if ($subject !== '' && $roles !== []) {
                return ['subject' => $subject, 'roles' => $roles, 'provider' => (string) ($session['provider'] ?? 'session')];
            }
        }

        $providers = is_array($sso['providers'] ?? null) ? $sso['providers'] : [];
        $proxy = is_array($providers['auth0-proxy'] ?? null) ? $providers['auth0-proxy'] : [];
        if (($proxy['enabled'] ?? false) === true) {
            $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $trusted = is_array($proxy['trusted_proxy_addresses'] ?? null)
                ? array_values(array_filter($proxy['trusted_proxy_addresses'], 'is_string'))
                : [];
            $subjectHeader = (string) ($proxy['subject_header'] ?? 'HTTP_X_OPUS_AUTH0_SUBJECT');
            $subject = trim((string) ($_SERVER[$subjectHeader] ?? ''));
            if ($subject !== '') {
                if (!in_array($remote, $trusted, true)) {
                    throw new \RuntimeException('OPUS_AUTH0_PROXY_ADDRESS_UNTRUSTED');
                }
                $secretEnv = trim((string) ($proxy['proxy_secret_env'] ?? ''));
                $expected = $secretEnv !== '' ? getenv($secretEnv) : false;
                $secretHeader = (string) ($proxy['secret_header'] ?? 'HTTP_X_OPUS_PROXY_SECRET');
                $provided = (string) ($_SERVER[$secretHeader] ?? '');
                if (!is_string($expected) || strlen($expected) < 32 || !hash_equals($expected, $provided)) {
                    throw new \RuntimeException('OPUS_AUTH0_PROXY_AUTHENTICATION_FAILED');
                }
                $rolesHeader = (string) ($proxy['roles_header'] ?? 'HTTP_X_OPUS_AUTH0_ROLES');
                $roles = array_values(array_filter(array_map('trim', explode(',', (string) ($_SERVER[$rolesHeader] ?? '')))));
                if ($roles === []) {
                    throw new \RuntimeException('OPUS_AUTH0_PROXY_ROLES_MISSING');
                }
                return ['subject' => $subject, 'roles' => $roles, 'provider' => 'auth0-proxy'];
            }
        }

        return ['subject' => 'anonymous', 'roles' => ['anonymous'], 'provider' => 'anonymous'];
    }

    /** @param array<string,mixed> $acl @param array{subject:string,roles:list<string>,provider:string} $identity */
    private function assertAllowed(array $acl, string $policyId, array $identity): void
    {
        $policies = is_array($acl['policies'] ?? null) ? $acl['policies'] : [];
        $policy = is_array($policies[$policyId] ?? null) ? $policies[$policyId] : null;
        if ($policy === null) {
            throw new \RuntimeException('OPUS_ACL_POLICY_UNKNOWN:' . $policyId);
        }
        $allowed = is_array($policy['roles'] ?? null)
            ? array_values(array_filter($policy['roles'], 'is_string'))
            : [];
        if (array_intersect($identity['roles'], $allowed) === []) {
            throw new \RuntimeException(
                $identity['subject'] === 'anonymous'
                    ? 'OPUS_AUTH_REQUIRED'
                    : 'OPUS_ACL_DENIED'
            );
        }
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $route @param array{subject:string,roles:list<string>,provider:string} $identity */
    private function transition(array $site, array $route, array $identity): string
    {
        $fsm = FsmSiteLoader::processorForSiteRoot($this->siteRoot);
        $target = trim((string) ($route['fsm_state'] ?? $route['state'] ?? ''));
        if (!$fsm->hasState($target)) {
            throw new \RuntimeException('OPUS_GENERATED_FSM_TARGET_UNKNOWN:' . $target);
        }
        $siteId = trim((string) ($site['site_id'] ?? 'site'));
        $sessionKey = 'opus_fsm_state_' . preg_replace('/[^a-z0-9_]/i', '_', $siteId);
        $current = trim((string) ($_SESSION[$sessionKey] ?? $fsm->initialState()));
        if (!$fsm->hasState($current)) {
            $current = $fsm->initialState();
        }
        if ($target !== $current) {
            $result = $fsm->transition($current, 'open_' . $target, ['identity' => $identity]);
            $current = (string) ($result['to_state'] ?? '');
        }
        $_SESSION[$sessionKey] = $current;
        return $current;
    }

    /** @param array<string,mixed> $site @param array<string,mixed> $routes @param array<string,mixed> $route @param array{subject:string,roles:list<string>,provider:string} $identity */
    private function renderPage(array $site, array $routes, array $route, string $state, string $locale, array $identity): string
    {
        $view = $this->safeRelative((string) ($route['view'] ?? ''));
        $template = $this->safeRelative((string) ($route['template'] ?? ''));
        $viewFile = $this->siteRoot . '/application/' . $view;
        if (!$this->file->exists($viewFile)) {
            throw new \RuntimeException('OPUS_GENERATED_VIEW_MODEL_MISSING');
        }
        $viewModel = require $viewFile;
        if (!is_array($viewModel)) {
            throw new \RuntimeException('OPUS_GENERATED_VIEW_MODEL_INVALID');
        }

        $module = trim((string) ($route['module'] ?? $state));
        $i18n = new ApplicationTranslationRuntime(
            $this->siteRoot . '/application',
            $module,
            $locale
        );
        $renderer = new ScoreTemplateRenderer(
            $this->siteRoot . '/application',
            $i18n
        );
        $page = is_array($viewModel['page'] ?? null)
            ? $viewModel['page']
            : [
                'title' => (string) ($viewModel['title'] ?? $state),
                'subtitle' => (string) ($viewModel['subtitle'] ?? ''),
            ];
        $titleKey = trim((string) ($route['title_key'] ?? 'page.title'));
        $subtitleKey = trim((string) ($route['subtitle_key'] ?? 'page.subtitle'));
        $page['title'] = $i18n->translate($titleKey);
        $page['subtitle'] = $i18n->translate($subtitleKey);

        $menu = '';
        foreach ((array) ($routes['routes'] ?? []) as $candidate) {
            if (!is_array($candidate) || ($candidate['show_in_menu'] ?? false) !== true) {
                continue;
            }
            $labelKey = (string) ($candidate['label'] ?? '');
            $label = $i18n->translate($labelKey);
            $path = (string) ($candidate['path'] ?? '/');
            $href = '/' . rawurlencode($locale) . ($path === '/' ? '' : $path);
            $menu .= $renderer->render(
                'default/templates/components/menu-item.score',
                ['menu_item' => [
                    'active_class' => ((string) ($candidate['fsm_state'] ?? '') === $state) ? 'is-active' : '',
                    'path' => $href,
                    'label' => $label,
                ]]
            );
        }

        $css = $renderer->render(
            'default/templates/components/stylesheet.score',
            ['asset' => ['href' => '/asset/css/default.css']]
        ) . $renderer->render(
            'default/templates/components/stylesheet.score',
            ['asset' => ['href' => '/asset/themes/' . rawurlencode((string) ($site['theme'] ?? 'starter')) . '/css/theme.css']]
        );
        $js = $renderer->render(
            'default/templates/components/script.score',
            ['asset' => ['src' => '/asset/themes/' . rawurlencode((string) ($site['theme'] ?? 'starter')) . '/js/theme.js']]
        );

        $data = array_replace_recursive($viewModel, [
            'lang' => $locale,
            'page' => $page,
            'site' => [
                'id' => (string) ($site['site_id'] ?? ''),
                'name' => (string) ($site['site_name'] ?? ''),
                'contract' => (string) ($site['contract'] ?? ''),
            ],
            'identity' => $identity,
            'menu_item' => [],
            'common' => ['menu' => $menu],
            'assets' => ['css' => $css, 'js' => $js],
        ]);
        $content = $renderer->render($template, $data);
        $data['content'] = $content;
        $data['common']['header'] = $renderer->render(
            'default/templates/components/header.score',
            $data
        );
        $data['common']['footer'] = $renderer->render(
            'default/templates/components/footer.score',
            $data
        );

        return $renderer->render('default/layouts/layout.score', $data);
    }

    private function renderError(string $code): string
    {
        try {
            $site = $this->config('config/site.json', 'OPUS_SITE_STANDARD_CONTRACT_CORE');
            $supported = is_array($site['locales'] ?? null)
                ? array_values(array_filter($site['locales'], 'is_string'))
                : [];
            $default = trim((string) ($site['default_locale'] ?? ''));
            $locale = BrowserLocaleNegotiator::forLocales($supported, $default)
                ->negotiate(
                    is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                        ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                        : null
                )->value;
            $i18n = new ApplicationTranslationRuntime(
                $this->siteRoot . '/application',
                'home',
                $locale
            );
            $renderer = new ScoreTemplateRenderer(
                $this->siteRoot . '/application',
                $i18n
            );
            $title = $i18n->translate('error.title');
            $message = $i18n->translate('error.request_failed');
            $content = $renderer->render(
                'default/templates/error.score',
                ['error' => ['title' => $title, 'message' => $message, 'code' => $code]]
            );
            return $renderer->render('default/layouts/layout.score', [
                'lang' => $locale,
                'page' => ['title' => $title],
                'site' => [
                    'name' => (string) ($site['site_name'] ?? 'OPUS'),
                    'contract' => (string) ($site['contract'] ?? ''),
                ],
                'common' => ['header' => '', 'footer' => ''],
                'assets' => ['css' => '', 'js' => ''],
                'content' => $content,
            ]);
        } catch (\Throwable $error) {
            throw new \RuntimeException('OPUS_GENERATED_ERROR_RENDER_FAILED', 0, $error);
        }
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \RuntimeException('OPUS_GENERATED_RELATIVE_PATH_INVALID');
        }
        return $path;
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_GENERATED_RUNTIME_FAILED';
    }
}

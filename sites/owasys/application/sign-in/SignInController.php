<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class SignInController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/sign-in';
    }

    public function title(): string
    {
        return 'Sign in';
    }

    public function group(): string
    {
        return 'Identity';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        $next = (string) ($_GET['next'] ?? '/opus-manager/create-site');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $next = (string) ($_POST['next'] ?? $next);

            if (OpusManagerAuth::signIn($username, $password)) {
                header('Location: ' . ($next !== '' ? $next : '/opus-manager/create-site') . (str_contains($next, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang), true, 302);
                return '';
            }

            return $this->renderStandaloneSignIn($lang, $next, 'Identifiant ou mot de passe invalide.');
        }

        return $this->renderStandaloneSignIn($lang, $next, '');
    }

    private function renderStandaloneSignIn(string $lang, string $next, string $error): string
    {
        $env = OpusManagerEnvironment::current();
        $errorHtml = $error !== '' ? '<p class="om-error">' . $this->h($error) . '</p>' : '';
        $prodBadge = OpusManagerEnvironment::isProd($env) ? '<span class="om-pill">PROD</span>' : '<span class="om-pill">DEV</span>';

        $html = '<!doctype html><html lang="' . $this->h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>Sign in — OPUS Manager</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body class="om-auth-page">';
        $html .= '<main class="om-auth-shell"><section class="om-card om-primary"><h1>OPUS Manager</h1><p>Connexion au manager OPUS.</p><div class="om-auth-badges">' . $prodBadge . '</div></section>';
        $html .= '<section class="om-card"><h2>Sign in</h2>' . $errorHtml;
        $html .= '<form class="om-form" method="post" action="/opus-manager/sign-in?lang=' . $this->h($lang) . '">';
        $html .= '<input type="hidden" name="next" value="' . $this->h($next) . '">';
        $html .= '<label>Identifiant<input name="username" autocomplete="username" required></label>';
        $html .= '<label>Mot de passe<input name="password" type="password" autocomplete="current-password" required></label>';
        $html .= '<button type="submit">Sign in</button></form>';
        $html .= '<p class="om-muted">Dev : admin / admin</p></section>';
        $html .= '<section class="om-card om-auth-lang"><h2>Changer la langue</h2><form method="get" action="/opus-manager/sign-in"><input type="hidden" name="next" value="' . $this->h($next) . '"><select name="lang" onchange="this.form.submit()">' . OpusManagerI18n::optionsHtml($lang) . '</select><noscript><button type="submit">OK</button></noscript></form></section>';
        $html .= '</main></body></html>';

        return $html;
    }
}
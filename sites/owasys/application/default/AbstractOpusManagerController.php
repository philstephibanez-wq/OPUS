<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;
use Opus\Manager\Service\OpusManagerModuleRegistry;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
abstract class AbstractOpusManagerController implements OpusManagerControllerInterface
{
    abstract public function route(): string;

    abstract public function title(): string;

    abstract public function group(): string;

    abstract public function isExpert(): bool;

    abstract public function render(array $context = []): string;

    protected function shell(string $title, string $body, array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        $env = OpusManagerEnvironment::current((string) ($context['env'] ?? 'dev'));
        $signedIn = array_key_exists('signed_in', $context) ? (bool) $context['signed_in'] : OpusManagerAuth::isSignedIn();
        $user = (string) ($context['user'] ?? OpusManagerAuth::user());

        $profiler = OpusManagerEnvironment::isProd($env)
            ? ''
            : '<span class="om-pill">DEV</span>';

        $auth = $signedIn
            ? '<span class="om-user">' . $this->h($user !== '' ? $user : 'admin') . '</span><a class="om-link" href="/opus-manager/logout?lang=' . $this->h($lang) . '">Sign out</a>'
            : '<a class="om-link" href="/opus-manager/sign-in?lang=' . $this->h($lang) . '">Sign in</a>';

        $html = '<!doctype html><html lang="' . $this->h($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>' . $this->h($title) . ' — OPUS Manager</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body>';
        $html .= '<div class="om-shell"><aside class="om-sidebar"><div class="om-brand"><strong>OPUS Manager</strong><span>Workspace</span></div>' . $this->navigation($lang) . '</aside>';
        $html .= '<main class="om-main"><header class="om-topbar"><div><h1>' . $this->h($title) . '</h1><p>OPUS Manager orchestre les briques OPUS sans recréer les moteurs.</p></div>';
        $html .= '<div class="om-env">' . $profiler . $auth . $this->languageSelector($lang) . '</div></header>';
        $html .= '<section class="om-content">' . $body . '</section></main></div></body></html>';

        return $html;
    }

    protected function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function navigation(string $lang): string
    {
        $groups = [];

        foreach (OpusManagerModuleRegistry::modules() as $module) {
            $group = (string) ($module['group'] ?? 'OPUS');
            $groups[$group][] = $module;
        }

        $html = '<nav class="om-nav">';
        foreach ($groups as $group => $modules) {
            $html .= '<section><h2>' . $this->h($group) . '</h2>';
            foreach ($modules as $module) {
                $route = (string) ($module['route'] ?? '#');
                $title = (string) ($module['title'] ?? $route);
                $summary = (string) ($module['summary'] ?? '');
                $html .= '<a href="' . $this->h($route) . '?lang=' . $this->h($lang) . '"><span>' . $this->h($title) . '</span><small>' . $this->h($summary) . '</small></a>';
            }
            $html .= '</section>';
        }

        return $html . '</nav>';
    }

    private function languageSelector(string $lang): string
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager/create-site'), PHP_URL_PATH) ?: '/opus-manager/create-site');

        return '<form class="om-lang" method="get" action="' . $this->h($path) . '"><label for="om-lang-select">Langue</label><select id="om-lang-select" name="lang" onchange="this.form.submit()">' . OpusManagerI18n::optionsHtml($lang) . '</select><noscript><button type="submit">OK</button></noscript></form>';
    }
}
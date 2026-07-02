<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerModuleRegistry;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
abstract class AbstractOpusManagerController implements OpusManagerControllerInterface
{
    protected function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function shell(string $title, string $body, array $context = []): string
    {
        $active = $this->route();
        $lang = (string) ($context['lang'] ?? 'fr');
        $env = (string) ($context['env'] ?? 'dev');
        $isProd = $env === 'prod';

        $nav = '';
        foreach (OpusManagerModuleRegistry::groupedModules() as $group => $modules) {
            $nav .= '<section class="om-nav-group"><h2>' . $this->h((string) $group) . '</h2><div>';
            foreach ($modules as $module) {
                $class = $module['route'] === $active ? ' class="is-active"' : '';
                $badge = $module['expert'] ? '<span>Expert</span>' : '';
                $nav .= '<a' . $class . ' href="' . $this->h($module['route']) . '?lang=' . $this->h($lang) . '">'
                    . '<strong>' . $this->h($module['title']) . '</strong>' . $badge . '</a>';
            }
            $nav .= '</div></section>';
        }

        $profiler = $isProd
            ? '<span class="om-prod-lock">Prod : profiler interdit</span>'
            : '<span class="om-dev-note">Dev/Staging : diagnostics contrôlés</span>';

        return '<!doctype html><html lang="' . $this->h($lang) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->h($title) . ' — OPUS Manager</title>'
            . '<link rel="stylesheet" href="/opus-manager-ui.css">'
            . '</head><body data-contract="OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE">'
            . '<header class="om-hero"><div><p class="om-kicker">OPUS Manager</p><h1>' . $this->h($title) . '</h1>'
            . '<p>Backoffice OPUS clair, modulaire et orienté création de site.</p></div>'
            . '<div class="om-env"><span>Langue : ' . $this->h($lang) . '</span>' . $profiler . '</div></header>'
            . '<main class="om-layout"><aside class="om-nav">' . $nav . '</aside><section class="om-content">' . $body . '</section></main>'
            . '</body></html>';
    }

    protected function moduleCard(string $summary, array $links = []): string
    {
        $html = '<section class="om-card"><h2>Rôle</h2><p>' . $this->h($summary) . '</p></section>';
        if ($links !== []) {
            $html .= '<section class="om-card"><h2>Réutilisation de l’existant</h2><p>Ce module ne recrée pas la logique métier. Il branche les routes et briques OPUS existantes.</p><div class="om-actions">';
            foreach ($links as $link) {
                $html .= '<a href="' . $this->h((string) $link['href']) . '">' . $this->h((string) $link['label']) . '</a>';
            }
            $html .= '</div></section>';
        }

        $html .= '<section class="om-card"><h2>Contrats</h2><ul>'
            . '<li>Auth centrale obligatoire.</li>'
            . '<li>ACL/RBAC centralisé.</li>'
            . '<li>Production sans profiler/debug.</li>'
            . '<li>I18N UE + ukrainien.</li>'
            . '<li>Composer install / no-dev validé.</li>'
            . '</ul></section>';

        return $html;
    }
}
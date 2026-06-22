<?php

#[AllowDynamicProperties]
class PackageContent_helper {
    private static string $lang = 'fr';

    public static function prepare($controller): void {
        $app = ASAP_Application::getInstance();
        $lang = isset($controller->lang) ? (string)$controller->lang : $app->getSiteDefaultLang();
        self::$lang = in_array($lang, array('fr','en','es'), true) ? $lang : 'fr';
    }

    public static function renderPage($controller, string $slug = ''): void {
        $app = ASAP_Application::getInstance();
        $slug = self::canonicalSlug($slug ?: ($app->getSite() ? $app->getSite()->getHomeSlug() : 'home'));
        [$title, $subtitle, $content, $aside] = self::page($slug);
        self::assignAndDraw($controller, $slug, $title, $subtitle, $content, $aside);
    }
    public static function renderDoc($controller, string $doc = ''): void { self::renderPage($controller, 'docs'); }

    private static function page(string $slug): array {
        switch ($slug) {
            case 'framework':
                return array('Framework ASAP', 'Démo intégrée dans un package autonome.', self::codeBlock("sites/demo/site.xml\nsites/demo/routes.xml\nsites/demo/controllers\nsites/demo/templates\nsites/demo/www\nsites/demo/logs"), self::asidePackage());
            case 'router':
                return array('Router', 'Routes du package demo seulement.', self::codeBlock("Mode dossier : /LOGANDPLAY_ASAP_LOCAL_PACKAGES/demo/...\nMode host : demo.logandplay.localhost/...\n-> SiteResolver\n-> sites/demo/routes.xml\n-> Index_controller"), self::asidePackage());
            case 'controllers':
                return array('Controllers', 'Le contrôleur de la démo est dans son package.', '<p><code>sites/demo/controllers/Index_controller.class.php</code></p><p>Il charge ses helpers et sa vue depuis <code>sites/demo/</code>.</p>', self::asidePackage());
            case 'views':
                return array('Views / Templates', 'Templates locaux au package.', '<p><code>sites/demo/views/Site_view.class.php</code></p><p><code>sites/demo/templates/site.tpl</code></p>', self::asidePackage());
            case 'models':
                return array('Models / DB', 'La démo reste sans base obligatoire.', '<p>ADOdb et mysqli restent disponibles dans le framework, mais cette vitrine ne force aucune connexion DB.</p>', self::asidePackage());
            case 'fsm':
                return array('FSM', 'Diagramme moderne sans GraphViz externe.', self::fsmDiagram(), self::asidePackage());
            case 'modules':
            case 'packages':
                return array('Modules / Packages', 'Organisation autonome de la démo ASAP.', self::cards(array(
                    array('Application', 'Contrôleurs, vues, templates et helpers.', self::url('/'.self::$lang.'/controllers')),
                    array('Public', 'CSS, JS, images et assets du package.', self::url('/'.self::$lang.'/views')),
                    array('Logs', 'Historique et traces isolés par package.', self::url('/'.self::$lang.'/debug-logs'))
                )), self::asidePackage());
            case 'i18n':
                return array('I18N', 'Routes FR/EN/ES + URL accentuée.', '<p>Exemple : <code>/fr/démo-interne</code></p><p>Les anciens liens métier ont été retirés.</p>', self::asidePackage());
            case 'acl':
                return array('ACL', 'Rôles, ressources, permissions.', self::cards(array(
                    array('Rôles', 'invité, staff, admin', '#'),
                    array('Ressources', 'pages, API, docs', '#'),
                    array('Décisions', 'allow / deny / conditions', '#'),
                )), self::asidePackage());
            case 'rest':
                return array('REST', 'Endpoint JSON local.', '<p>Teste <code>/api/site</code> dans ce package.</p>'.self::codeBlock('GET '.self::url('/api/site')), self::asidePackage());
            case 'debug':
                return array('Debug', 'Logs séparés par site.', '<p>Les logs de cette démo vont dans <code>sites/demo/logs/</code>.</p>', self::asidePackage());
            case 'urls-accentuées':
            case 'i18n-url':
                return array('URLs accentuées', 'Signature historique ASAP, modernisée UTF-8.', '<p>Route active : <code>/fr/démo-interne</code></p><p>Le router matche les chemins UTF-8 sans traduction legacy forcée.</p>', self::asidePackage());
            case 'articles':
                return array('Articles de démonstration', 'Liste statique pilotée par le package.', self::cards(array(
                    array('Bootstrap ASAP', 'Entrée, configuration et router.', self::url('/'.self::$lang.'/framework')),
                    array('Router XML', 'Routes déclaratives et UTF-8.', self::url('/'.self::$lang.'/router')),
                    array('FSM', 'Workflow piloté par états.', self::url('/'.self::$lang.'/fsm'))
                )), self::asidePackage());
            case 'gallery':
            case 'galerie':
                return array('Galerie', 'Exemple d’assets internes du package.', '<div class="grid photo-grid"><a class="photo-card" href="'.htmlspecialchars(self::url('/'.self::$lang.'/galerie'),ENT_QUOTES,'UTF-8').'"><img src="'.htmlspecialchars(rtrim(ASAP_Application::getInstance()->getThemeUrl(), '/').'/assets/demo/placeholder.svg',ENT_QUOTES,'UTF-8').'" alt="Photo demo"><span>Photo demo</span></a></div>', self::asidePackage());
            case 'contact':
                return array('Contact demo', 'Prévu pour Mailpit local.', '<div class="notice">SMTP local : <code>127.0.0.1:1025</code>. UI : <code>localhost:8025</code>.</div>', self::asidePackage());
            case 'demo-interne':
                return array('Démo interne accentuée', 'URL accentuée interne neutre.', '<p>Cette page remplace les anciennes routes métier de test.</p><p>Chemin : <code>/fr/démo-interne</code></p>', self::asidePackage());
            default:
                return array('ASAP Demo', 'Démo technique intégrée dans <code>sites/demo</code>.', self::cards(array(
                    array('Framework', 'Vue d’ensemble du package.', self::url('/'.self::$lang.'/framework')),
                    array('Router', 'Routes UTF-8 et multi-site.', self::url('/'.self::$lang.'/router')),
                    array('REST', 'API /api/site.', self::url('/'.self::$lang.'/rest')),
                    array('ACL', 'Rôles et permissions.', self::url('/'.self::$lang.'/acl')),
                    array('FSM', 'Diagramme SVG inline.', self::url('/'.self::$lang.'/fsm')),
                    array('URL accentuée', 'Démo interne UTF-8.', self::url('/fr/démo-interne')),
                )), self::asidePackage());
        }
    }

    private static function assignAndDraw($controller, string $active, string $title, string $subtitle, string $content, string $aside): void {
        $app = ASAP_Application::getInstance();

        // V13: rendu autonome de la démo.
        // On garde le routage/framework ASAP, mais on ne dépend plus du header
        // hérité ASAP_VIEW_Html ni d'un lien CSS externe fragile. Cela garantit
        // que la présentation de la démo ASAP/PHP8 reste intacte en mode
        // domaine dédié, Cloudflare Tunnel ou rewrite UwAmp.
        $html = self::buildStandaloneDocument(
            $title,
            $subtitle,
            $content,
            $aside,
            $active,
            $app
        );

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo $html;
    }

    private static function buildStandaloneDocument(string $title, string $subtitle, string $content, string $aside, string $active, $app): string {
        $siteId = $app->getSiteId();
        $siteName = $app->getSiteLabel();
        $siteKind = strtoupper($app->getSite()->getKind());
        $fullTitle = $title . ' — ASAP Demo';
        $css = self::loadInlineCss($app);
        $jsUrl = rtrim($app->getThemeUrl(), '/') . '/js/site.js';

        return '<!doctype html>' . "\n"
            . '<html lang="' . self::e(self::$lang) . '">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . self::e($fullTitle) . '</title>' . "\n"
            . '<style>' . "\n" . $css . "\n" . '</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '<div id="demo-app" lang="' . self::e(self::$lang) . '" data-site="' . self::e($siteId) . '">' . "\n"
            . '    <header class="site-header">' . "\n"
            . '        <div class="brand-row">' . "\n"
            . '            <div>' . "\n"
            . '                <p class="eyebrow">ASAP / PHP 8 Demo</p>' . "\n"
            . '                <h1><a href="' . self::e(self::url('/' . self::$lang)) . '">' . self::e($siteName) . '</a></h1>' . "\n"
            . '            </div>' . "\n"
            . '            <div class="header-tools">' . "\n"
            . '                <div class="site-switch">' . self::siteSwitchHtml() . '</div>' . "\n"
            . '                <div class="lang-switch">' . self::langSwitchHtml(true) . '</div>' . "\n"
            . '                <div class="date-pill">' . self::e(date('d/m/Y')) . '</div>' . "\n"
            . '            </div>' . "\n"
            . '        </div>' . "\n"
            .          self::menuHtml() . "\n"
            . '    </header>' . "\n"
            . '    <main class="layout">' . "\n"
            . '        <section class="main-panel">' . "\n"
            . '            <div class="page-heading">' . "\n"
            . '                <p class="eyebrow">' . self::e($active) . '</p>' . "\n"
            . '                <h2>' . self::e($title) . '</h2>' . "\n"
            . '                <p>' . self::allowCode($subtitle) . '</p>' . "\n"
            . '            </div>' . "\n"
            .              $content . "\n"
            . '        </section>' . "\n"
            . '        <aside class="side-panel">' . "\n"
            .              $aside . "\n"
            . '        </aside>' . "\n"
            . '    </main>' . "\n"
            . '    <footer class="site-footer">' . "\n"
            . '        <p>© ' . self::e(date('Y')) . ' ASAP Framework — package <code>' . self::e($siteId) . '</code> servi par Log&amp;Play.</p>' . "\n"
            . '        <p><a href="' . self::e(self::url('/fr/démo-interne')) . '">URL accentuée</a> · <a href="' . self::e(self::url('/api/site')) . '">API site</a></p>' . "\n"
            . '    </footer>' . "\n"
            . '</div>' . "\n"
            . '<script src="' . self::e($jsUrl) . '" defer></script>' . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    private static function loadInlineCss($app): string {
        $cssFile = rtrim($app->getSitePublicPath(), '/\\') . '/css/style.css';
        if (is_file($cssFile)) {
            $css = file_get_contents($cssFile);
            if (is_string($css) && trim($css) !== '') {
                return $css;
            }
        }

        return 'body{margin:0;font-family:"Trebuchet MS",Arial,sans-serif;background:#111318;color:#20242a}'
            . '#demo-app{width:min(1160px,calc(100% - 40px));margin:24px auto}'
            . '.site-header,.main-panel,.side-panel,.site-footer{background:#fff;border-radius:20px;padding:24px;margin-bottom:20px}'
            . '.brand-row{display:flex;justify-content:space-between;gap:20px}.demo-nav ul,.switch{list-style:none;display:flex;gap:10px;flex-wrap:wrap;padding:0;margin:0}'
            . '.layout{display:grid;grid-template-columns:1fr 320px;gap:24px}.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.card{display:block;background:#fff;border:1px solid #e2e7ef;border-radius:18px;padding:20px}'
            . '@media(max-width:880px){.layout,.cards{grid-template-columns:1fr}.brand-row{flex-direction:column}}';
    }

    private static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function allowCode(string $value): string {
        // Les sous-titres de la démo utilisent seulement <code> de façon contrôlée.
        return strip_tags($value, '<code>');
    }

    private static function canonicalSlug(string $slug): string {
        $slug = trim(rawurldecode($slug));
        $map = array(''=>'home','accueil'=>'home','inicio'=>'home','framework'=>'framework','router'=>'router','controllers'=>'controllers','views'=>'views','templates'=>'views','models'=>'models','models-db'=>'models','fsm'=>'fsm','i18n'=>'i18n','debug'=>'debug','debug-logs'=>'debug','acl'=>'acl','rest'=>'rest','urls-accentuées'=>'urls-accentuées','utf8-urls'=>'urls-accentuées','urls-acentuadas'=>'urls-accentuées','démo-interne'=>'demo-interne','demo-interne'=>'demo-interne','internal-demo'=>'demo-interne','demo-interna'=>'demo-interne','contact'=>'contact');
        return $map[$slug] ?? $slug;
    }
    private static function menuHtml(): string { return '<nav class="demo-nav" aria-label="Navigation ASAP Demo">' . self::links(array('Accueil'=>self::url('/'.self::$lang), 'Framework'=>self::url('/'.self::$lang.'/framework'), 'Router'=>self::url('/'.self::$lang.'/router'), 'Controllers'=>self::url('/'.self::$lang.'/controllers'), 'Views'=>self::url('/'.self::$lang.'/views'), 'Models/DB'=>self::url('/'.self::$lang.'/models-db'), 'REST'=>self::url('/'.self::$lang.'/rest'), 'ACL'=>self::url('/'.self::$lang.'/acl'), 'FSM'=>self::url('/'.self::$lang.'/fsm'), 'I18N'=>self::url('/'.self::$lang.'/i18n'), 'URL accentuée'=>self::url('/fr/démo-interne'), 'Contact'=>self::url('/'.self::$lang.'/contact')), 'menu') . '</nav>'; }
    private static function siteSwitchHtml(): string { return self::links(array('Log&Play'=>self::siteUrl('logandplay', '/'), 'Demo'=>self::siteUrl('demo', '/'), 'Maestro'=>self::siteUrl('maestro', '/')), 'switch'); }
    private static function langSwitchHtml(bool $markCurrent = false): string { return self::links(array('FR'=>self::url('/fr'),'EN'=>self::url('/en'),'ES'=>self::url('/es')), 'switch', $markCurrent ? strtoupper(self::$lang) : ''); }
    private static function url(string $path = '', ?string $siteId = null): string { $app = ASAP_Application::getInstance(); return $app->getSiteUrl($siteId ?: $app->getSiteId(), $path); }
    private static function siteUrl(string $siteId, string $path = '/'): string { return ASAP_Application::getInstance()->getSiteUrl($siteId, $path); }
    private static function links(array $items, string $class='menu', string $currentLabel=''): string { $out='<ul class="'.$class.'">'; foreach($items as $label=>$href){ $isCurrent = ($currentLabel !== '' && strtoupper((string)$label) === strtoupper($currentLabel)); $out.='<li'.($isCurrent ? ' class="active"' : '').'><a'.($isCurrent ? ' class="current"' : '').' href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a></li>'; } return $out.'</ul>'; }
    private static function cards(array $cards): string { $html='<div class="cards">'; foreach($cards as $c){ [$t,$d,$h]=$c; $html.='<a class="card" href="'.htmlspecialchars($h,ENT_QUOTES,'UTF-8').'"><h3>'.htmlspecialchars($t,ENT_QUOTES,'UTF-8').'</h3><p>'.htmlspecialchars($d,ENT_QUOTES,'UTF-8').'</p><span>Ouvrir →</span></a>'; } return $html.'</div>'; }
    private static function codeBlock(string $text): string { return '<pre class="code">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</pre>'; }
    private static function asidePackage(): string { return '<h3>Package demo</h3><p><code>sites/demo/</code><br><code>sites/demo/www/</code><br><code>sites/demo/logs/</code><br><code>sites/demo/tmp/</code></p>'; }
    private static function fsmDiagram(): string { if (class_exists('ASAP_FSM_Diagram')) { return ASAP_FSM_Diagram::renderDemoHtml(); } return self::codeBlock('BOOT -> ROUTE -> CTRL -> VIEW -> HTML'); }
}

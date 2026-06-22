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

    public static function renderDoc($controller, string $doc = ''): void {
        $doc = self::safeDocSlug($doc ?: 'index');
        [$title, $subtitle, $content, $aside] = self::docPage($doc);
        self::assignAndDraw($controller, 'docs', $title, $subtitle, $content, $aside);
    }

    private static function page(string $slug): array {
        switch ($slug) {
            case 'docs':
                return self::docPage('index');
            case 'architecture':
                return array('Architecture Maestro', 'Synthèse locale servie par ASAP.', '<p>Ce package prépare l’intégration contrôlée de la documentation Maestro.</p>'.self::docIndex(), self::asidePackage());
            case 'handoff':
                return array('Handoff Maestro', 'Historique et points de reprise.', '<p>Les handoffs restent des points stables de reprise. Le site public affiche uniquement ce qui est explicitement exposé.</p>', self::asidePackage());
            case 'demo-interne':
                return array('Démo interne accentuée', 'URL UTF-8 interne au package Maestro.', '<p>Route : <code>/fr/démo-interne</code></p>', self::asidePackage());
            default:
                return array('Maestro Docs', 'Documentation Maestro intégrée comme package ASAP.', self::cards(array(
                    array('Documentation', 'Index local contrôlé.', self::url('/'.self::$lang.'/docs')),
                    array('Architecture', 'Synthèse Maestro.', self::url('/'.self::$lang.'/architecture')),
                    array('Handoff', 'Points de reprise.', self::url('/'.self::$lang.'/handoff')),
                    array('API Site', 'Diagnostic JSON.', self::url('/api/site')),
                )).self::docIndex(), self::asidePackage());
        }
    }

    private static function docPage(string $doc): array {
        if ($doc === 'index') {
            return array('Documentation Maestro', 'Index local contrôlé.', self::docIndex(), self::asidePackage());
        }
        $content = self::readDoc($doc);
        return array('Document Maestro', 'Fichier rendu par ASAP : '.$doc, $content ?: '<p>Document non trouvé dans la whitelist locale.</p>'.self::docIndex(), self::asidePackage());
    }

    private static function readDoc(string $doc): string {
        $base = self::docsPath();
        if ($base === '') { return ''; }
        foreach (array('.html', '.md', '.txt') as $ext) {
            $file = $base . '/' . $doc . $ext;
            if (is_file($file)) {
                $text = file_get_contents($file);
                if ($text === false) { return ''; }
                if ($ext === '.html') { return '<div class="doc-render">'.$text.'</div>'; }
                return '<pre class="doc-pre">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</pre>';
            }
        }
        return '';
    }

    private static function docIndex(): string {
        $base = self::docsPath();
        if ($base === '') {
            return '<div class="notice">Aucun dossier docs trouvé. Place des fichiers dans <code>sites/maestro/docs</code> ou ajuste <code>site.xml</code>.</div>';
        }
        $items = array();
        foreach (glob($base.'/*.{html,md,txt}', GLOB_BRACE) ?: array() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $items[] = '<li><a href="'.htmlspecialchars(self::url('/'.self::$lang.'/doc/'.rawurlencode($name)), ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</a></li>';
        }
        if (!$items) { return '<div class="notice">Dossier docs présent, mais aucun fichier <code>.html/.md/.txt</code>.</div>'; }
        return '<h3>Documents disponibles</h3><ul class="doc-list">'.implode('', $items).'</ul>';
    }

    private static function docsPath(): string {
        $app = ASAP_Application::getInstance();
        $site = $app->getSite();
        $paths = array();
        if ($site && $site->getDocPath() !== '') { $paths[] = $site->getPackagePath() . '/' . trim($site->getDocPath(), '/'); }
        $paths[] = 'D:/UwAmp/www/Maestro_v5/DOC/Maestro_v5';
        foreach ($paths as $path) {
            $path = str_replace('\\', '/', $path);
            if (is_dir($path)) { return rtrim($path, '/'); }
        }
        return '';
    }

    private static function assignAndDraw($controller, string $active, string $title, string $subtitle, string $content, string $aside): void {
        $app = ASAP_Application::getInstance();
        $controller->newTitle($title . ' — Maestro Docs');
        $controller->newHtmlLang(self::$lang);
        $controller->newLang(self::$lang);
        $controller->newBrandMark('M');
        $controller->newSiteId($app->getSiteId());
        $controller->newSiteName($app->getSiteLabel());
        $controller->newSiteKind(strtoupper($app->getSite()->getKind()));
        $controller->newActive($active);
        $controller->newPageTitle($title);
        $controller->newSubtitle($subtitle);
        $controller->newContentHtml($content);
        $controller->newAsideHtml($aside);
        $controller->newMenu(self::menuHtml());
        $controller->newSiteSwitch(self::siteSwitchHtml());
        $controller->newLangSwitch(self::langSwitchHtml());
        $controller->newHomeUrl(self::url('/' . self::$lang));
        $controller->newAccentUrl(self::url('/fr/démo-interne'));
        $controller->newApiUrl(self::url('/api/site'));
        $controller->newFooterYear(date('Y'));
        $view = new Site_view();
        $view->draw();
    }

    private static function canonicalSlug(string $slug): string { $slug=trim(rawurldecode($slug)); $map=array(''=>'home','docs'=>'docs','documentation'=>'docs','architecture'=>'architecture','handoff'=>'handoff','démo-interne'=>'demo-interne','demo-interne'=>'demo-interne','internal-demo'=>'demo-interne'); return $map[$slug] ?? $slug; }
    private static function safeDocSlug(string $doc): string { return preg_replace('/[^A-Za-z0-9_.-]/', '', rawurldecode($doc)) ?: 'index'; }
    private static function menuHtml(): string { return self::links(array('Home'=>self::url('/'.self::$lang), 'Docs'=>self::url('/'.self::$lang.'/docs'), 'Architecture'=>self::url('/'.self::$lang.'/architecture'), 'Handoff'=>self::url('/'.self::$lang.'/handoff'), 'API'=>self::url('/api/site'))); }
    private static function siteSwitchHtml(): string { return self::links(array('Log&Play'=>self::siteUrl('logandplay', '/'), 'Demo'=>self::siteUrl('demo', '/'), 'Maestro'=>self::siteUrl('maestro', '/')), 'switch'); }
    private static function langSwitchHtml(): string { return self::links(array('FR'=>self::url('/fr'),'EN'=>self::url('/en'),'ES'=>self::url('/es')), 'switch'); }
    private static function url(string $path = '', ?string $siteId = null): string { $app = ASAP_Application::getInstance(); return $app->getSiteUrl($siteId ?: $app->getSiteId(), $path); }
    private static function siteUrl(string $siteId, string $path = '/'): string { return ASAP_Application::getInstance()->getSiteUrl($siteId, $path); }
    private static function links(array $items, string $class='menu'): string { $out='<ul class="'.$class.'">'; foreach($items as $label=>$href){ $out.='<li><a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a></li>'; } return $out.'</ul>'; }
    private static function cards(array $cards): string { $html='<div class="cards">'; foreach($cards as $c){ [$t,$d,$h]=$c; $html.='<a class="card" href="'.htmlspecialchars($h,ENT_QUOTES,'UTF-8').'"><h3>'.htmlspecialchars($t,ENT_QUOTES,'UTF-8').'</h3><p>'.htmlspecialchars($d,ENT_QUOTES,'UTF-8').'</p><span>Ouvrir →</span></a>'; } return $html.'</div>'; }
    private static function asidePackage(): string { return '<h3>Package maestro</h3><p><code>sites/maestro/</code><br><code>sites/maestro/docs/</code><br><code>sites/maestro/logs/</code></p>'; }
}

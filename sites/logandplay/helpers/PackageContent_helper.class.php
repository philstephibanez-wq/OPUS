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

    public static function renderDoc($controller, string $doc = ''): void { self::renderPage($controller, 'maestro'); }

    private static function page(string $slug): array {
        switch ($slug) {
            case 'asap':
            case 'framework':
                return array('ASAP Framework', 'Le cœur commun mutualisé, auto-configuré par packages de sites.', self::cards(array(
                    array('Package demo', 'La démo technique vit dans sites/demo.', self::siteUrl('demo', '/')),
                    array('Package Maestro', 'La documentation Maestro vit dans sites/maestro.', self::siteUrl('maestro', '/')),
                    array('API Site', 'Diagnostic du site résolu.', self::url('/api/site')),
                )).self::codeBlock("framework/ = commun\nsites/<site>/ = package autonome\nsites/<site>/www = assets publics du site\nsites/<site>/logs = logs séparés"), self::asideArchitecture());
            case 'maestro':
                return array('Maestro', 'Portail local vers le package documentation Maestro.', self::cards(array(
                    array('Ouvrir Maestro Docs', 'Package dédié, via préfixe local ou host.', self::siteUrl('maestro', '/')),
                    array('Index docs', 'Route /fr/docs dans le package maestro.', self::siteUrl('maestro', '/fr/docs')),
                )), self::asideHosts());
            case 'demo':
                return array('Démo ASAP', 'La démo existante est intégrée comme package autonome.', self::cards(array(
                    array('Ouvrir la démo', 'Package dédié, via préfixe local ou host.', self::siteUrl('demo', '/')),
                    array('URL accentuée', 'Route UTF-8 interne au package demo.', self::siteUrl('demo', '/fr/démo-interne')),
                )), self::asideHosts());
            case 'contact':
                return array('Contact local', 'Prévu pour Mailpit en développement local.', '<div class="notice">SMTP local : <code>127.0.0.1:1025</code>. Interface Mailpit : <code>localhost:8025</code>.</div>', self::asideMailpit());
            case 'demo-interne':
                return array('Démo interne accentuée', 'Route UTF-8 neutre, conservée sans anciens liens métier.', '<p>Cette page prouve que le site principal sait router une URL accentuée : <code>/fr/démo-interne</code>.</p>', self::asideArchitecture());
            default:
                return array('Log&Play local', 'Un seul framework ASAP, plusieurs sites-packages autonomes.', self::cards(array(
                    array('ASAP Framework', 'Architecture multi-site à packages.', self::url('/'.self::$lang.'/asap')),
                    array('ASAP Demo', 'Démo technique séparée.', self::siteUrl('demo', '/')),
                    array('Maestro Docs', 'Documentation Maestro séparée.', self::siteUrl('maestro', '/')),
                    array('URL accentuée', 'Route interne UTF-8.', self::url('/fr/démo-interne')),
                )), self::asideHosts());
        }
    }

    private static function assignAndDraw($controller, string $active, string $title, string $subtitle, string $content, string $aside): void {
        $app = ASAP_Application::getInstance();
        $controller->newTitle($title . ' — ' . $app->getSiteLabel());
        $controller->newHtmlLang(self::$lang);
        $controller->newLang(self::$lang);
        $controller->newBrandMark('L&P');
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

    private static function canonicalSlug(string $slug): string {
        $slug = trim(rawurldecode($slug));
        $map = array(''=>'home','accueil'=>'home','inicio'=>'home','asap'=>'asap','framework'=>'asap','maestro'=>'maestro','demo'=>'demo','contact'=>'contact','démo-interne'=>'demo-interne','demo-interne'=>'demo-interne','internal-demo'=>'demo-interne','demo-interna'=>'demo-interne');
        return $map[$slug] ?? $slug;
    }
    private static function menuHtml(): string { return self::links(array('home'=>self::url('/'.self::$lang), 'ASAP'=>self::url('/'.self::$lang.'/asap'), 'Demo'=>self::url('/'.self::$lang.'/demo'), 'Maestro'=>self::url('/'.self::$lang.'/maestro'), 'Contact'=>self::url('/'.self::$lang.'/contact'))); }
    private static function siteSwitchHtml(): string { return self::links(array('Log&Play'=>self::siteUrl('logandplay', '/'), 'Demo'=>self::siteUrl('demo', '/'), 'Maestro'=>self::siteUrl('maestro', '/')), 'switch'); }
    private static function langSwitchHtml(): string { return self::links(array('FR'=>self::url('/fr'),'EN'=>self::url('/en'),'ES'=>self::url('/es')), 'switch'); }
    private static function url(string $path = '', ?string $siteId = null): string { $app = ASAP_Application::getInstance(); return $app->getSiteUrl($siteId ?: $app->getSiteId(), $path); }
    private static function siteUrl(string $siteId, string $path = '/'): string { return ASAP_Application::getInstance()->getSiteUrl($siteId, $path); }
    private static function links(array $items, string $class='menu'): string { $out='<ul class="'.$class.'">'; foreach($items as $label=>$href){ $out.='<li><a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a></li>'; } return $out.'</ul>'; }
    private static function cards(array $cards): string { $html='<div class="cards">'; foreach($cards as $c){ [$t,$d,$h]=$c; $html.='<a class="card" href="'.htmlspecialchars($h,ENT_QUOTES,'UTF-8').'"><h3>'.htmlspecialchars($t,ENT_QUOTES,'UTF-8').'</h3><p>'.htmlspecialchars($d,ENT_QUOTES,'UTF-8').'</p><span>Explorer →</span></a>'; } return $html.'</div>'; }
    private static function codeBlock(string $text): string { return '<pre class="code">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</pre>'; }
    private static function asideHosts(): string { return '<h3>Hosts locaux</h3><p><code>/</code><br><code>/demo/</code><br><code>/maestro/</code><br><small>ou hosts locaux dédiés si activés</small></p>'; }
    private static function asideArchitecture(): string { return '<h3>Package</h3><p>Ce site vit dans <code>sites/logandplay/</code> avec son <code>www/</code>, ses <code>logs/</code> et son <code>tmp/</code>.</p>'; }
    private static function asideMailpit(): string { return '<h3>Mailpit</h3><p>SMTP local : <code>127.0.0.1:1025</code><br>UI : <code>localhost:8025</code></p>'; }
}

<?php

#[AllowDynamicProperties]
class ASAP_SITE_Site {
    protected string $_id;
    protected string $_label;
    protected array $_hosts;
    protected string $_pathPrefix;
    protected string $_theme;
    protected string $_defaultLang;
    protected string $_homeSlug;
    protected string $_kind;
    protected string $_docPath;
    protected string $_description;
    protected string $_resolvedHost;
    protected string $_resolutionMode;
    protected string $_packagePath;
    protected string $_routesFile;
    protected string $_publicDir;
    protected string $_logsDir;
    protected string $_tmpDir;

    public function __construct(string $id, array $data = array(), string $resolvedHost = '', string $packagePath = '') {
        $this->_id = $id;
        $this->_label = (string)($data['label'] ?? ucfirst($id));
        $hosts = (string)($data['hosts'] ?? '');
        $this->_hosts = array_values(array_filter(array_map('trim', explode(',', strtolower($hosts)))));
        $this->_pathPrefix = self::normalizePathPrefix((string)($data['pathPrefix'] ?? ($id === 'logandplay' ? '' : $id)));
        $this->_theme = (string)($data['theme'] ?? $id);
        $this->_defaultLang = (string)($data['defaultLang'] ?? 'fr');
        $this->_homeSlug = (string)($data['homeSlug'] ?? 'home');
        $this->_kind = (string)($data['kind'] ?? $id);
        $this->_docPath = (string)($data['docPath'] ?? '');
        $this->_description = (string)($data['description'] ?? '');
        $this->_resolvedHost = self::normalizeHost($resolvedHost);
        $this->_resolutionMode = (string)($data['resolutionMode'] ?? 'default');
        $this->_packagePath = rtrim(str_replace('\\', '/', $packagePath), '/');
        $this->_routesFile = (string)($data['routes'] ?? 'routes.xml');
        $this->_publicDir = (string)($data['public'] ?? 'www');
        $this->_logsDir = (string)($data['logs'] ?? 'logs');
        $this->_tmpDir = (string)($data['tmp'] ?? 'tmp');
    }

    public function getId(): string { return $this->_id; }
    public function getLabel(): string { return $this->_label; }
    public function getHosts(): array { return $this->_hosts; }
    public function getPathPrefix(): string { return $this->_pathPrefix; }
    public function getTheme(): string { return $this->_theme; }
    public function getDefaultLang(): string { return $this->_defaultLang; }
    public function getHomeSlug(): string { return $this->_homeSlug; }
    public function getKind(): string { return $this->_kind; }
    public function getDocPath(): string { return $this->_docPath; }
    public function getDescription(): string { return $this->_description; }
    public function getHost(): string { return $this->_resolvedHost; }
    public function getResolutionMode(): string { return $this->_resolutionMode; }
    public function getPackagePath(): string { return $this->_packagePath; }
    public function getRoutesPath(): string { return $this->_resolvePackagePath($this->_routesFile); }
    public function getPublicPath(): string { return $this->_resolvePackagePath($this->_publicDir); }
    public function getLogsPath(): string { return $this->_resolvePackagePath($this->_logsDir); }
    public function getTmpPath(): string { return $this->_resolvePackagePath($this->_tmpDir); }

    public function setRuntimeContext(string $resolvedHost, string $resolutionMode): void {
        $this->_resolvedHost = self::normalizeHost($resolvedHost);
        $this->_resolutionMode = $resolutionMode !== '' ? $resolutionMode : 'default';
    }

    protected function _resolvePackagePath(string $path): string {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') { return $this->_packagePath; }
        if (preg_match('#^[A-Za-z]:/#', $path) || substr($path, 0, 1) === '/') { return rtrim($path, '/'); }
        return rtrim($this->_packagePath . '/' . trim($path, '/'), '/');
    }

    public function matchesHost(string $host): bool {
        $host = self::normalizeHost($host);
        foreach ($this->_hosts as $configured) {
            if ($configured === $host) { return true; }
        }
        return false;
    }

    public static function normalizeHost(string $host): string {
        $host = strtolower(trim($host));
        if (strpos($host, ',') !== false) { $host = trim(explode(',', $host, 2)[0]); }
        if (strpos($host, ':') !== false && substr_count($host, ':') === 1) { $host = explode(':', $host, 2)[0]; }
        return $host;
    }

    public static function normalizePathPrefix(string $prefix): string {
        $prefix = trim(rawurldecode(str_replace('\\', '/', $prefix)));
        $prefix = trim($prefix, '/');
        if ($prefix === '' || $prefix === '.') { return ''; }
        return preg_replace('#/+#', '/', $prefix) ?? $prefix;
    }

    public function ensureRuntimeDirs(): void {
        foreach (array($this->getLogsPath(), $this->getTmpPath()) as $dir) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        }
    }

    public function toArray(): array {
        return array(
            'id' => $this->_id,
            'label' => $this->_label,
            'hosts' => $this->_hosts,
            'pathPrefix' => $this->_pathPrefix,
            'theme' => $this->_theme,
            'defaultLang' => $this->_defaultLang,
            'homeSlug' => $this->_homeSlug,
            'kind' => $this->_kind,
            'docPath' => $this->_docPath,
            'description' => $this->_description,
            'resolvedHost' => $this->_resolvedHost,
            'resolutionMode' => $this->_resolutionMode,
            'packagePath' => $this->_packagePath,
            'routesPath' => $this->getRoutesPath(),
            'publicPath' => $this->getPublicPath(),
            'logsPath' => $this->getLogsPath(),
            'tmpPath' => $this->getTmpPath(),
        );
    }
}

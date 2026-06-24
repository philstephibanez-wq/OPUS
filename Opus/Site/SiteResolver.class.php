<?php

#[AllowDynamicProperties]
/**
 * Legacy OPUS site resolver.
 *
 * Resolves the current site context for legacy OPUS runtime execution.
 */
class OPUS_SITE_SiteResolver {
    public static function resolve($packagesConfig, string $defaultSiteId = 'logandplay', string $basePath = '', ?array &$catalog = null): OPUS_SITE_Site {
        $packages = is_array($packagesConfig) ? $packagesConfig : array();
        $host = self::detectHost();
        $sites = array();

        foreach ($packages as $id => $packageData) {
            $site = self::loadSitePackage((string)$id, $packageData, $host);
            if ($site instanceof OPUS_SITE_Site) {
                $sites[$site->getId()] = $site;
            }
        }
        $catalog = $sites;

        // 1) Dedicated host wins. Example: demo.logandplay.localhost must always
        //    resolve to the demo package, even if a weird path is appended.
        foreach ($sites as $id => $site) {
            if ($id !== $defaultSiteId && $site->matchesHost($host)) {
                return self::finalize($site, $host, 'host');
            }
        }

        // 2) Path-prefix mode. This enables one local folder without vhosts:
        //    /LOGANDPLAY_OPUS_LOCAL_PACKAGES/demo/...    -> demo
        //    /LOGANDPLAY_OPUS_LOCAL_PACKAGES/maestro/... -> maestro
        $pathSite = self::resolveByPathPrefix($sites, $basePath);
        if ($pathSite instanceof OPUS_SITE_Site) {
            return self::finalize($pathSite, $host, 'path');
        }

        // 3) Default host. On generic localhost/127.0.0.1, keep path mode so all
        //    generated links stay inside the same project folder.
        if (isset($sites[$defaultSiteId]) && $sites[$defaultSiteId]->matchesHost($host)) {
            $mode = self::isGenericLocalHost($host) ? 'path' : 'host';
            return self::finalize($sites[$defaultSiteId], $host, $mode);
        }

        // 4) Any other configured host.
        foreach ($sites as $site) {
            if ($site->matchesHost($host)) {
                return self::finalize($site, $host, 'host');
            }
        }

        if (isset($sites[$defaultSiteId])) {
            return self::finalize($sites[$defaultSiteId], $host, self::isGenericLocalHost($host) ? 'path' : 'default');
        }

        $first = reset($sites);
        if ($first instanceof OPUS_SITE_Site) {
            return self::finalize($first, $host, self::isGenericLocalHost($host) ? 'path' : 'default');
        }

        $fallback = new OPUS_SITE_Site('logandplay', array(
            'label' => 'Log&Play',
            'hosts' => 'logandplay.localhost,localhost,127.0.0.1',
            'pathPrefix' => '',
            'theme' => 'logandplay',
            'defaultLang' => 'fr',
            'kind' => 'portal',
            'routes' => 'routes.xml',
            'public' => 'www',
            'logs' => 'logs',
            'tmp' => 'tmp',
        ), $host, defined('ROOT') ? ROOT . '/sites/logandplay' : '');
        return self::finalize($fallback, $host, self::isGenericLocalHost($host) ? 'path' : 'default');
    }

    protected static function finalize(OPUS_SITE_Site $site, string $host, string $mode): OPUS_SITE_Site {
        $site->setRuntimeContext($host, $mode);
        $site->ensureRuntimeDirs();
        return $site;
    }

    protected static function resolveByPathPrefix(array $sites, string $basePath): ?OPUS_SITE_Site {
        $path = self::requestPathAfterBase($basePath);
        $trimmed = trim($path, '/');
        if ($trimmed === '') { return null; }
        $first = explode('/', $trimmed, 2)[0];
        $first = OPUS_SITE_Site::normalizePathPrefix($first);
        if ($first === '') { return null; }

        foreach ($sites as $site) {
            $prefix = $site->getPathPrefix();
            if ($prefix !== '' && strcasecmp($prefix, $first) === 0) {
                return $site;
            }
        }
        return null;
    }

    protected static function requestPathAfterBase(string $basePath): string {
        $request = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $queryPos = strpos($request, '?');
        if ($queryPos !== false) { $request = substr($request, 0, $queryPos); }
        $request = '/' . trim(rawurldecode(str_replace('\\', '/', $request)), '/');
        if ($request === '//') { $request = '/'; }

        $basePath = self::normalizeBasePath($basePath);
        $candidates = array($basePath);
        if (defined('ROOT')) {
            $rootName = basename(str_replace('\\', '/', rtrim((string)ROOT, '/\\')));
            if ($rootName !== '') { $candidates[] = '/' . $rootName; }
        }
        foreach (array_unique(array_filter($candidates)) as $candidate) {
            $candidate = self::normalizeBasePath($candidate);
            if ($candidate !== '' && ($request === $candidate || strpos($request, $candidate . '/') === 0)) {
                $rest = substr($request, strlen($candidate));
                return $rest === '' ? '/' : '/' . trim($rest, '/');
            }
        }
        return $request;
    }

    protected static function normalizeBasePath(string $path): string {
        $path = trim(rawurldecode(str_replace('\\', '/', $path)));
        if ($path === '' || $path === '/') { return ''; }
        return '/' . trim($path, '/');
    }

    protected static function loadSitePackage(string $id, $packageData, string $host): ?OPUS_SITE_Site {
        $path = '';
        if (is_array($packageData)) { $path = (string)($packageData['path'] ?? ''); }
        elseif (is_string($packageData)) { $path = $packageData; }
        if ($path === '') { return null; }
        $siteXml = self::rootPath($path);
        if (!is_file($siteXml)) { throw new OPUS_Exception('OPUS site package manifest not found: ' . $siteXml); }
        $data = self::parseSiteXml($siteXml);
        $siteId = (string)($data['id'] ?? $id);
        unset($data['id']);
        return new OPUS_SITE_Site($siteId, $data, $host, dirname($siteXml));
    }

    protected static function parseSiteXml(string $file): array {
        if (!class_exists('SimpleXMLElement')) { throw new OPUS_Exception('PHP SimpleXML is required to load site package: ' . $file); }
        $xml = simplexml_load_file($file);
        if (!$xml) { throw new OPUS_Exception('Invalid OPUS site package manifest: ' . $file); }
        $data = array('id' => (string)$xml['id']);
        foreach ($xml->children() as $key => $child) { $data[(string)$key] = (string)$child; }
        return $data;
    }

    protected static function rootPath(string $path): string {
        $path = str_replace('\\', '/', trim($path));
        if (preg_match('#^[A-Za-z]:/#', $path) || substr($path, 0, 1) === '/') { return $path; }
        return rtrim((string)ROOT, '/\\') . '/' . ltrim($path, '/');
    }

    public static function detectHost(): string {
        $candidates = array($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '', $_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? '');
        foreach ($candidates as $candidate) {
            $host = OPUS_SITE_Site::normalizeHost((string)$candidate);
            if ($host !== '') { return $host; }
        }
        return 'localhost';
    }

    public static function isGenericLocalHost(string $host): bool {
        $host = OPUS_SITE_Site::normalizeHost($host);
        return in_array($host, array('localhost', '127.0.0.1', '::1'), true);
    }
}

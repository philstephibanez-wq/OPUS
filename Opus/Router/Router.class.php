<?php

#[AllowDynamicProperties]
/**
 * OPUS router.
 *
 * Routes OPUS requests before the modern runtime routing layer takes over.
 */
class OPUS_Router {
    private const ROUTE_DEBUG_ENABLED = false;
    private const ROUTE_DEBUG_LOG = '/logs/opus_route_debug.log';
    private const ROUTE_DEBUG_MAX_BYTES = 524288;

    protected $_app = null;
    protected $_i18n = null;
    protected $_links = array();
    public $params = array();
    protected $_siteDir = '/';
    protected $_sitePathPrefix = '';
    protected $_siteResolutionMode = '';
    protected $_routes = array();
    protected $_request_uri = '/';
    protected $_request_parts = array();
    protected $_conditions = array();
    protected $_target = array();

    public function __construct($app, $siteDir, $routes) {
        $this->_app = OPUS_Application::getInstance();
        $this->_i18n = OPUS_I18N_I18n::getInstance();
        $this->_routes = is_array($routes) ? $routes : array();
        $this->_siteDir = $this->_normalizeBasePath((string)$siteDir);
        if ($this->_siteDir === '') {
            $this->_siteDir = $this->_detectBasePath();
        }
        $this->_sitePathPrefix = method_exists($this->_app, 'getSitePathPrefix') ? OPUS_SITE_Site::normalizePathPrefix((string)$this->_app->getSitePathPrefix()) : '';
        $this->_siteResolutionMode = method_exists($this->_app, 'getSiteResolutionMode') ? (string)$this->_app->getSiteResolutionMode() : '';
        $this->_debugLog('construct', array(
            'siteDir' => $this->_siteDir,
            'sitePathPrefix' => $this->_sitePathPrefix,
            'siteResolutionMode' => $this->_siteResolutionMode,
            'routesType' => gettype($routes),
            'routeCount' => count($this->_routes),
            'requestUri' => $_SERVER['REQUEST_URI'] ?? '',
            'scriptName' => $_SERVER['SCRIPT_NAME'] ?? '',
            'phpSelf' => $_SERVER['PHP_SELF'] ?? '',
            'documentRoot' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'root' => defined('ROOT') ? ROOT : '',
        ));
    }

    private function _debugLog(string $event, $payload = null): void {
        if (!self::ROUTE_DEBUG_ENABLED || !defined('ROOT')) {
            return;
        }

        $dir = rtrim((string)ROOT, '/\\') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = rtrim((string)ROOT, '/\\') . self::ROUTE_DEBUG_LOG;
        if (is_file($file) && filesize($file) > self::ROUTE_DEBUG_MAX_BYTES) {
            @rename($file, $file . '.1');
        }

        $line = '[' . date('c') . '] ROUTER ' . $event;
        if ($payload !== null) {
            $line .= ' ' . $this->_debugEncode($payload);
        }
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function _debugEncode($payload): string {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($json)) {
            return str_replace(array("\r", "\n"), ' ', print_r($payload, true));
        }
        return $json;
    }

    private function _debugRouteSample(int $limit = 80): array {
        $sample = array();
        $count = 0;
        foreach ($this->_routes as $name => $route) {
            if ($count >= $limit) {
                break;
            }
            $sample[] = array(
                'id' => (string)$name,
                'rule' => isset($route->rule) ? (string)$route->rule : '',
                'target' => isset($route->target) ? $route->target : array(),
            );
            $count++;
        }
        return $sample;
    }

    private function _normalizeBasePath($path): string {
        $path = trim(str_replace('\\', '/', (string)$path));
        if ($path === '' || $path === '/') {
            return '';
        }
        return '/' . trim($path, '/');
    }

    private function _normalizeRoutePath($path): string {
        $path = rawurldecode((string)$path);
        $path = $this->_normalizeUnicode((string)$path);
        $path = str_replace('\\', '/', $path);
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function _normalizeUnicode(string $value): string {
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }
        return $value;
    }

    private function _detectBasePath(): string {
        $rootName = defined('ROOT') ? basename(str_replace('\\', '/', rtrim((string)ROOT, '/\\'))) : '';
        $request = (string)($_SERVER['REQUEST_URI'] ?? '');
        $requestPath = $request;
        $queryPos = strpos($requestPath, '?');
        if ($queryPos !== false) {
            $requestPath = substr($requestPath, 0, $queryPos);
        }
        $requestPath = rawurldecode(str_replace('\\', '/', $requestPath));
        if ($rootName !== '') {
            $marker = '/' . $rootName;
            if ($requestPath === $marker || strpos($requestPath, $marker . '/') === 0) {
                return $this->_normalizeBasePath($marker);
            }
        }

        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }
        $scriptName = str_replace('\\', '/', $scriptName);
        $dir = dirname($scriptName);
        if ($dir === false || $dir === '.' || $dir === '/' || $dir === '\\') {
            return '';
        }

        // Apache may rewrite to /www/index.php. The application base URL is
        // still the project root, not the public directory.
        $dir = str_replace('\\', '/', (string)$dir);
        if (substr($dir, -4) === '/www') {
            $dir = substr($dir, 0, -4);
        }

        return $this->_normalizeBasePath($dir);
    }

    private function _stripBasePath($request, $basePath): string {
        $request = '/' . ltrim((string)$request, '/');
        $candidates = array(
            $this->_normalizeBasePath($basePath),
            $this->_detectBasePath(),
        );

        if (defined('ROOT')) {
            $rootName = basename(str_replace('\\', '/', rtrim((string)ROOT, '/\\')));
            if ($rootName !== '') {
                $candidates[] = '/' . $rootName;
            }
        }

        $seen = array();
        $normalizedCandidates = array();
        foreach ($candidates as $candidate) {
            $candidate = $this->_normalizeBasePath($candidate);
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            $normalizedCandidates[] = $candidate;
            if ($request === $candidate) {
                $this->_debugLog('strip-base', array(
                    'request' => $request,
                    'basePath' => $basePath,
                    'candidates' => $normalizedCandidates,
                    'matchedCandidate' => $candidate,
                    'result' => '/',
                ));
                return '/';
            }
            if (strpos($request, $candidate . '/') === 0) {
                $result = substr($request, strlen($candidate));
                $this->_debugLog('strip-base', array(
                    'request' => $request,
                    'basePath' => $basePath,
                    'candidates' => $normalizedCandidates,
                    'matchedCandidate' => $candidate,
                    'result' => $result,
                ));
                return $result;
            }
        }

        $this->_debugLog('strip-base-no-match', array(
            'request' => $request,
            'basePath' => $basePath,
            'candidates' => $normalizedCandidates,
            'result' => $request,
        ));
        return $request;
    }


    private function _stripSitePathPrefix(string $request): string {
        if ($this->_siteResolutionMode !== 'path' || $this->_sitePathPrefix === '') {
            return $request;
        }
        $request = '/' . ltrim($request, '/');
        $prefix = '/' . trim($this->_sitePathPrefix, '/');
        if ($request === $prefix) {
            $this->_debugLog('strip-site-prefix', array(
                'request' => $request,
                'sitePathPrefix' => $this->_sitePathPrefix,
                'result' => '/',
            ));
            return '/';
        }
        if (strpos($request, $prefix . '/') === 0) {
            $result = substr($request, strlen($prefix));
            $this->_debugLog('strip-site-prefix', array(
                'request' => $request,
                'sitePathPrefix' => $this->_sitePathPrefix,
                'result' => $result,
            ));
            return $result === '' ? '/' : $result;
        }
        return $request;
    }

    private function _cleanupURL(): void {
        $request = $_SERVER['REQUEST_URI'] ?? '/';
        $rawRequest = $request;
        $pos = strpos($request, '?');
        if ($pos !== false) {
            $request = substr($request, 0, $pos);
        }

        $strippedRequest = $this->_stripBasePath($request, $this->_siteDir);
        $siteStrippedRequest = $this->_stripSitePathPrefix($strippedRequest);
        $normalizedRequest = $this->_normalizeRoutePath($siteStrippedRequest);

        $request_parts = explode('/', trim($normalizedRequest, '/'));
        if ($normalizedRequest === '/') {
            $request_parts = array();
        }

        /*
         * OPUS legacy router used to translate every URL segment through I18N
         * before route matching. That breaks modern localized routes because
         * a route like /fr/framework becomes /Français/framework when the
         * dictionary contains a translation for "fr".
         *
         * Modern OPUS routes are already declared in their public localized
         * form inside config.xml. Therefore the dispatcher must match the
         * decoded URL as-is, after base path stripping and UTF-8 normalization.
         */
        $legacyTranslatedParts = array();
        foreach ($request_parts as $part) {
            $legacyTranslatedParts[] = $part === '' ? '' : $this->_i18n->translate($part, 1, 'N', true);
        }

        $this->_request_parts = $request_parts;
        $this->_request_uri = $normalizedRequest === '/' ? '/' : '/' . implode('/', $this->_request_parts);

        $this->_debugLog('cleanup-url', array(
            'rawRequest' => $rawRequest,
            'requestNoQuery' => $request,
            'siteDir' => $this->_siteDir,
            'sitePathPrefix' => $this->_sitePathPrefix,
            'siteResolutionMode' => $this->_siteResolutionMode,
            'strippedRequest' => $strippedRequest,
            'siteStrippedRequest' => $siteStrippedRequest,
            'normalizedRequest' => $normalizedRequest,
            'routeParts' => $request_parts,
            'legacyTranslatedPartsIgnored' => $legacyTranslatedParts,
            'finalRequestUri' => $this->_request_uri,
        ));
    }

    private function _map($key) {
        if (!isset($this->_conditions[$key], $this->_target[$key])) {
            return false;
        }
        $target_parts = explode('|', $this->_target[$key]);
        $conditions_parts = explode('|', $this->_conditions[$key]);
        if (count($target_parts) !== count($conditions_parts)) {
            throw new OPUS_Exception("TARGET PARTS DON'T MATCH CONDITIONS PART for key $key");
        }
        return array_combine($conditions_parts, $target_parts);
    }

    private function _parameterRegex(string $key): string {
        if ($key === 'page') {
            if (array_key_exists($key, $this->_conditions)) {
                return '(' . $this->_conditions[$key] . ')';
            }
            if (isset($this->_target[$key])) {
                return '(' . $this->_target[$key] . ')';
            }
            return '([^/]+)';
        }
        if (array_key_exists($key, $this->_conditions)) {
            return '(' . $this->_conditions[$key] . ')';
        }
        return '([^/]+)';
    }

    private function _buildRouteRegex(string $rule, array &$parameterNames): string {
        $rule = $this->_normalizeRoutePath($rule);
        $parameterNames = array();

        if ($rule === '/') {
            return '#^/$#u';
        }

        $quoted = preg_quote($rule, '#');
        $quoted = preg_replace_callback('#\\\\:([A-Za-z_][A-Za-z0-9_]*)#', function ($matches) use (&$parameterNames) {
            $key = $matches[1];
            $parameterNames[] = $key;
            return $this->_parameterRegex($key);
        }, $quoted);

        return '#^' . $quoted . '/?$#u';
    }

    public function regex_url($matches): string {
        $key = trim(str_replace(':', '', $matches[0]), '/');
        return $this->_parameterRegex($key);
    }

    public function execute(): array {
        $this->_cleanupURL();
        $this->_debugLog('execute-start', array(
            'requestUri' => $this->_request_uri,
            'routeCount' => count($this->_routes),
            'routeSample' => $this->_debugRouteSample(80),
        ));

        foreach ($this->_routes as $name => $route) {
            foreach (array('page', 'controller', 'action') as $required) {
                if (!isset($route->target[$required])) {
                    $this->_debugLog('route-invalid-target', array(
                        'routeId' => (string)$name,
                        'required' => $required,
                        'rule' => isset($route->rule) ? (string)$route->rule : '',
                        'target' => isset($route->target) ? $route->target : array(),
                    ));
                    throw new OPUS_Exception("TARGET: $required is NOT DEFINED for route name: $name");
                }
            }

            $this->_conditions = isset($route->conditions) && is_array($route->conditions) ? $route->conditions : array();
            $this->_target = $route->target;

            $p_names = array();
            $url_regex = $this->_buildRouteRegex((string)$route->rule, $p_names);

            if (!preg_match($url_regex, $this->_request_uri, $p_values)) {
                continue;
            }

            array_shift($p_values);
            $result = $this->_target;
            foreach ($p_names as $num => $key) {
                $map = $this->_map($key);
                if (!$map) {
                    if (isset($p_values[$num]) && $p_values[$num] !== '') {
                        $result[$key] = $p_values[$num];
                    }
                    continue;
                }
                $value = $p_values[$num] ?? '';
                if (isset($map[$value]) && $map[$value] !== '') {
                    $result[$key] = $map[$value];
                } elseif ($value !== '') {
                    $result[$key] = $value;
                } elseif (isset($result[$key]) && strpos($result[$key], '|') !== false) {
                    $result[$key] = substr($result[$key], 0, strpos($result[$key], '|'));
                }
            }

            $this->_debugLog('match', array(
                'routeId' => (string)$name,
                'rule' => (string)$route->rule,
                'regex' => $url_regex,
                'parameterNames' => $p_names,
                'parameterValues' => $p_values,
                'result' => $result,
            ));
            return $result;
        }

        $this->_debugLog('no-match', array(
            'requestUri' => $this->_request_uri,
            'routeCount' => count($this->_routes),
            'routeSample' => $this->_debugRouteSample(120),
        ));
        return array();
    }

}

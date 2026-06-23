<?php

define('APP_COLOR', 'orange');
define('TODO', 'red');

#[AllowDynamicProperties]
class OPUS_Application {

    private const ROUTE_DEBUG_ENABLED = false;
    private const ROUTE_DEBUG_LOG = '/logs/opus_route_debug.log';
    private const ROUTE_DEBUG_MAX_BYTES = 524288;

    private static $_instance = null;     // php5.3
    private static $_output = '';
     
    public $response;
    public $config;
    public $router;
    
    private $_env = false;
    private $_https = false;
    private $_protocol = 'http';
    private $_useRouter = false;
    private $_routerParams;
    private $_routes = array();
    private $_module;
    private $_modulePath;
    private $_controllerClass = '';
    private $_siteUrl = '';
    private $_sitePath = '';
    private $_siteDir = '';
    private $_public = 'www/';
    private $_assets = 'assets/';
    private $_configRoutes;
    private $_site = null;
    private $_sites = array();
    private $_bootFsm = null;


    /**
     * Initialize and execute the mandatory boot FSM.
     *
     * Contract:
     * - no FSM, no engine
     * - boot is a FSM program
     * - Application owns the runtime boot FSM
     */
    private function _initBootFsm(): void {
        $siteKey = PHP_SAPI === 'cli' ? 'cli' : $this->_detectRequestHost();
        $siteKey = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string)$siteKey);
        if ($siteKey === '') {
            $siteKey = 'default';
        }

        $this->_bootFsm = new OPUS_FSM_Boot('opus_boot_' . $siteKey);
        $this->_bootFsm->runBoot();
    }

    public function getBootFsm() {
        return $this->_bootFsm;
    }

    private function _routeDebugLog(string $event, $payload = null): void {
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

        $line = '[' . date('c') . '] APP ' . $event;
        if ($payload !== null) {
            $line .= ' ' . $this->_routeDebugEncode($payload);
        }
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function _routeDebugEncode($payload): string {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($json)) {
            return str_replace(array("\r", "\n"), ' ', print_r($payload, true));
        }
        return $json;
    }

    final public function __construct() {

        OPUS_Application::$_instance = $this;
        $this->_initBootFsm();
        ob_start("OPUS_Application::output_handler");
        
        $ip = str_replace(':', '_', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

        //LANGUAGES
        $langs = explode(';', strtolower(($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "fr")));
        $lang = $langs[0];
        $langParts = explode(',', $lang);
        $defaultLang = $langParts[0] ?? 'fr';
        $defaultLang_long = $langParts[1] ?? $defaultLang;
        if($defaultLang == '') $defaultLang = 'fr';
        
        // charger la config correspondant au signal
//        $xmlFile = ROOT . "/application/config/config.$defaultLang.xml";
        $xmlFile = ROOT . "/application/config/config.xml";
        $PHPconfig = OPUS_ConfigLoader::getConfig($xmlFile);

        require_once ($PHPconfig);        
        $this->config = new Config();            

        // OPUS multi-site resolver: one codebase can serve several local hosts
        // and one local folder through path prefixes (/demo, /maestro).
        $initialSiteDir = $this->_detectBasePath();
        $this->_sites = array();
        $this->_site = OPUS_SITE_SiteResolver::resolve($this->config->get('sitePackages'), (string)$this->config->get('defaultSite'), $initialSiteDir, $this->_sites);
        if ($this->_site instanceof OPUS_SITE_Site) {
            $this->config->set('theme', $this->_site->getTheme());
            $this->config->set('currentSite', $this->_site->toArray());
        }

        $debug = $this->config->getEnv("debug");

        if ($debug) {
            OPUS_Debug::setDebug($debug, $this->getSiteLogDir());
            OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " DEBUG IS STARTED !!!!!", __FILE__, __LINE__, TODO);
        }

        $configuredSiteDir = (string)$this->config->getEnv("siteDir");
        $autoSiteDir = $this->_detectBasePath();

        // In dedicated-host mode (demo.logandplay.org, maestro.logandplay.org,
        // logandplay.org), the public URL base must be the domain root.  A
        // previous local/root rewrite can leave /LogAndPlay.org in REQUEST_URI;
        // keeping it here makes generated links leak the installation folder
        // and breaks CSS/JS assets.  Folder mode is still preserved for generic
        // localhost / 127.0.0.1.
        if ($configuredSiteDir === ''
            && $this->_site instanceof OPUS_SITE_Site
            && $this->_site->getResolutionMode() === 'host') {
            $autoSiteDir = '';
        }

        $this->_siteDir = $configuredSiteDir !== '' ? $this->_normalizeWebPath($configuredSiteDir) : $autoSiteDir;
        if ($configuredSiteDir === '' && $this->_siteDir !== '') {
            $this->config->setEnv("siteDir", $this->_siteDir);
        }

        $configuredSiteUrl = (string)$this->config->getEnv("siteUrl");
        $siteUrl = $this->_resolvePublicSiteUrl($configuredSiteUrl);
        $this->_siteUrl = $this->_joinUrlPath($siteUrl, $this->_currentSiteWebPath());
        // Keep filesystem root independent from an auto-detected HTTP base path.
        // When siteDir is explicitly configured, preserve the historical behaviour.
        $this->_sitePath = rtrim((string)$this->config->getEnv("rootPath"), '/\\') . '/';
        if ($configuredSiteDir !== '') {
            $this->_sitePath = rtrim($this->_sitePath, '/\\') . '/' . trim(str_replace('\\', '/', $this->_siteDir), '/') . '/';
        }
        $this->_public = $this->config->getEnv("public");
        $this->_assets = $this->config->getEnv("assets");

        $this->_routeDebugLog('bootstrap', array(
            'requestUri' => $_SERVER['REQUEST_URI'] ?? '',
            'scriptName' => $_SERVER['SCRIPT_NAME'] ?? '',
            'phpSelf' => $_SERVER['PHP_SELF'] ?? '',
            'documentRoot' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'httpHost' => $_SERVER['HTTP_HOST'] ?? '',
            'forwardedHost' => $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '',
            'forwardedProto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
            'https' => $_SERVER['HTTPS'] ?? '',
            'serverPort' => $_SERVER['SERVER_PORT'] ?? '',
            'root' => defined('ROOT') ? ROOT : '',
            'configuredSiteDir' => $configuredSiteDir,
            'autoSiteDir' => $autoSiteDir,
            'finalSiteDir' => $this->_siteDir,
            'configuredSiteUrl' => $configuredSiteUrl,
            'finalSiteUrl' => $this->_siteUrl,
            'sitePath' => $this->_sitePath,
            'public' => $this->_public,
            'assets' => $this->_assets,
        ));

//        OPUS_Debug::addDump(__CLASS__ . "::" . __FUNCTION__ . " \$this->config ", $this->config, __FILE__, __LINE__, APP_COLOR);
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " FAIRE un POKE de la config ?????", __FILE__, __LINE__, TODO);

        if ($this->config->get('router')) {
            $this->_configRoutes = $this->_loadCurrentSiteRoutes();
        }
        $this->_routeDebugLog('routes-loaded', array(
            'routerEnabled' => (bool)$this->config->get('router'),
            'routesType' => gettype($this->_configRoutes),
            'routeCount' => is_array($this->_configRoutes) ? count($this->_configRoutes) : 0,
            'firstRouteIds' => is_array($this->_configRoutes) ? array_slice(array_keys($this->_configRoutes), 0, 40) : array(),
        ));
        $this->router = new OPUS_Router($this, $this->_siteDir, $this->_configRoutes);

//        parent::__construct($id, 'INIT', null, array(), array(), false);
        if (!($this->_bootFsm instanceof OPUS_FSM_Boot) || !$this->_bootFsm->isReady()) {
            throw new OPUS_Exception('OPUS boot FSM did not reach BOOT_READY. Runtime dispatch is forbidden.');
        }
        $this->dispatch();
    }

   public static function getInstance (){
      if(self::$_instance === null){
         return self::$_instance = new OPUS_Application();
      }
      return self::$_instance;
   }

    public function run() {
        $this->response = null;
    }

    // site en maintenance ?
    protected function isOff() {
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " " . ROOT . "/application/config/maintenance", __FILE__, __LINE__, TODO);
        if (file_exists(ROOT . "/application/config/maintenance"))
            return true;
        return false;
    }

    protected function create_controller_instance($class, $params) {
        $reflection_class = new ReflectionClass($class);
        return $reflection_class->newInstanceArgs($params);
    }

    private function _normalizeWebPath($path): string {
        $path = trim(str_replace('\\', '/', (string)$path));
        if ($path === '' || $path === '/') {
            return '';
        }
        return '/' . trim($path, '/');
    }

    private function _detectBasePath(): string {
        // First use REQUEST_URI + the project directory name. Behind reverse
        // proxies / rewrites, SCRIPT_NAME can be just /index.php, while the
        // public request is still /opus_php85/...
        $rootName = basename(str_replace('\\', '/', rtrim((string)ROOT, '/\\')));
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
                return $this->_normalizeWebPath($marker);
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

        return $this->_normalizeWebPath($dir);
    }

    private function _joinUrlPath($baseUrl, $path): string {
        $baseUrl = rtrim((string)$baseUrl, '/');
        $path = $this->_normalizeWebPath($path);
        return $baseUrl . ($path !== '' ? $path : '') . '/';
    }


    private function _joinWebPaths(string ...$parts): string {
        $out = '';
        foreach ($parts as $part) {
            $part = trim(str_replace('\\', '/', $part));
            if ($part === '' || $part === '/') { continue; }
            $out .= '/' . trim($part, '/');
        }
        return $out === '' ? '' : $out;
    }

    private function _currentSiteWebPath(): string {
        if ($this->_site instanceof OPUS_SITE_Site && $this->_site->getResolutionMode() === 'path') {
            return $this->_joinWebPaths($this->_siteDir, $this->_site->getPathPrefix());
        }
        return $this->_siteDir;
    }

    private function _isGenericLocalHost(string $host): bool {
        return class_exists('OPUS_SITE_SiteResolver') && OPUS_SITE_SiteResolver::isGenericLocalHost($host);
    }

    private function _preferPathModeLinks(): bool {
        if ($this->_site instanceof OPUS_SITE_Site && $this->_site->getResolutionMode() === 'path') {
            return true;
        }
        return $this->_isGenericLocalHost($this->_detectRequestHost()) && $this->_siteDir !== '';
    }

    private function _sitePathFor(OPUS_SITE_Site $site): string {
        if ($this->_preferPathModeLinks()) {
            return $this->_joinWebPaths($this->_siteDir, $site->getPathPrefix());
        }
        return $this->_siteDir;
    }

    private function _siteHostFor(OPUS_SITE_Site $site): string {
        $hosts = $site->getHosts();
        foreach ($hosts as $host) {
            if (!$this->_isGenericLocalHost($host)) {
                return $host;
            }
        }
        return $this->_detectRequestHost();
    }

    private function _cleanRelativeUrlPath(string $path): string {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || $path === '/') { return ''; }
        return '/' . trim($path, '/');
    }

    public function getSiteUrl($siteId = null, string $path = ''): string {
        $siteId = $siteId === null || $siteId === '' ? $this->getSiteId() : (string)$siteId;
        $site = $this->_sites[$siteId] ?? null;
        if (!($site instanceof OPUS_SITE_Site)) {
            $site = $this->_site;
        }
        if (!($site instanceof OPUS_SITE_Site)) {
            return rtrim($this->getUrl(), '/') . $this->_cleanRelativeUrlPath($path);
        }

        $this->_protocol = $this->_detectRequestProtocol();
        $host = $this->_preferPathModeLinks() ? $this->_detectRequestHost() : $this->_siteHostFor($site);
        $basePath = $this->_sitePathFor($site);
        return $this->_protocol . '://' . $host . $this->_joinWebPaths($basePath, $path);
    }

    private function _resolvePublicSiteUrl($configuredSiteUrl): string {
        $configuredSiteUrl = trim((string)$configuredSiteUrl);
        if (!$this->_shouldAutoDetectSiteUrl($configuredSiteUrl)) {
            return $configuredSiteUrl;
        }

        $host = $this->_detectRequestHost();
        return '//' . $host . '/';
    }

    private function _shouldAutoDetectSiteUrl($configuredSiteUrl): bool {
        $configuredSiteUrl = trim((string)$configuredSiteUrl);
        if ($configuredSiteUrl === '' || strtoupper($configuredSiteUrl) === '{AUTO}') {
            return true;
        }

        $host = $this->_extractHostFromSiteUrl($configuredSiteUrl);
        if ($host === '') {
            return true;
        }

        $host = strtolower($host);
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.localhost');
    }

    private function _extractHostFromSiteUrl($siteUrl): string {
        $siteUrl = trim((string)$siteUrl);
        if ($siteUrl === '' || strtoupper($siteUrl) === '{AUTO}') {
            return '';
        }
        if (substr($siteUrl, 0, 2) === '//') {
            $siteUrl = 'http:' . $siteUrl;
        }
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = trim($siteUrl, '/');
        }
        if (strpos($host, ':') !== false && substr_count($host, ':') === 1) {
            $host = explode(':', $host, 2)[0];
        }
        return $host;
    }

    private function _detectRequestHost(): string {
        $candidates = array(
            $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '',
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['SERVER_NAME'] ?? '',
        );

        foreach ($candidates as $candidate) {
            $host = trim((string)$candidate);
            if ($host === '') {
                continue;
            }
            if (strpos($host, ',') !== false) {
                $parts = explode(',', $host);
                $host = trim($parts[0]);
            }
            $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host);
            if ($host !== '') {
                return $host;
            }
        }

        return 'localhost';
    }

    private function _detectRequestProtocol(): string {
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if (strpos($forwardedProto, ',') !== false) {
            $parts = explode(',', $forwardedProto);
            $forwardedProto = strtolower(trim($parts[0]));
        }
        if ($forwardedProto === 'https' || $forwardedProto === 'http') {
            return $forwardedProto;
        }

        $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
        if ($cfVisitor !== '' && stripos($cfVisitor, 'https') !== false) {
            return 'https';
        }

        if (isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
            return 'https';
        }
        if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return 'https';
        }

        return 'http';
    }

    private function _loadCurrentSiteRoutes(): array {
        if (!($this->_site instanceof OPUS_SITE_Site)) {
            return array();
        }
        return $this->_loadRoutesFromXml($this->_site->getRoutesPath());
    }

    private function _loadRoutesFromXml(string $file): array {
        if ($file === '' || !is_file($file)) {
            throw new OPUS_Exception('Site routes XML file not found: ' . $file);
        }
        if (!class_exists('SimpleXMLElement')) {
            throw new OPUS_Exception('PHP extension simplexml/xml is required to load site routes: ' . $file);
        }
        $xml = simplexml_load_file($file);
        if (!$xml) {
            throw new OPUS_Exception('Invalid site routes XML: ' . $file);
        }
        $routes = array();
        foreach ($xml->route as $node) {
            $id = (string)$node['id'];
            if ($id === '') { continue; }
            $target = $this->_xmlChildrenToArray(isset($node->target) ? $node->target : null);
            $conditions = $this->_xmlChildrenToArray(isset($node->conditions) ? $node->conditions : null);
            $routes[$id] = new Route_Object(
                $id,
                (string)(isset($node->rule) ? $node->rule : '/'),
                $target,
                $conditions,
                (string)(isset($node->menu) ? $node->menu : ''),
                (string)(isset($node->method) ? $node->method : 'layout')
            );
        }
        return $routes;
    }

    private function _xmlChildrenToArray($node): array {
        $out = array();
        if (!$node) { return $out; }
        foreach ($node->children() as $key => $child) {
            $out[(string)$key] = (string)$child;
        }
        return $out;
    }

    public function getSite() {
        return $this->_site;
    }

    public function getSiteId(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getId() : '';
    }

    public function getSitesCatalog(): array {
        return $this->_sites;
    }

    public function getSitePathPrefix(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getPathPrefix() : '';
    }

    public function getSiteResolutionMode(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getResolutionMode() : '';
    }

    public function getSiteLabel(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getLabel() : '';
    }

    public function getSiteHost(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getHost() : '';
    }

    public function getSiteDefaultLang(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getDefaultLang() : 'fr';
    }

    public function getSitePackagePath(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getPackagePath() : '';
    }

    public function getSitePublicPath(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getPublicPath() : '';
    }

    public function getSiteLogDir(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getLogsPath() : rtrim((string)ROOT, '/\\') . '/logs';
    }

    public function getSiteTmpDir(): string {
        return ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getTmpPath() : rtrim((string)ROOT, '/\\') . '/tmp';
    }

    public function getProtocol() {
        return $this->_protocol;
    }

     public function getDomain() {
        return $this->_siteUrl;
    }   
    
    public function getControllerClass() {
        return $this->_controllerClass;
    }

    public function getModulePath() {
        return $this->_modulePath;
    }
    public function getModule() {
        return $this->_module;
    }
    
    public function getRoutesMenu($menu) {
        $routes = array(); 
        foreach ($this->_configRoutes as $name => $route) {
           if ($route->menu == $menu) $routes[] = $route;          
        }
        return $routes;
    }

    public function getConfigRoutes($key) {
        if ($key == '') {
            return $this->_configRoutes;
        }
        return $this->_configRoutes[$key];
    }

    protected function _getController() {
        return $this->getRouterParam('controller');
    }

    protected function _getAction() {
        return $this->getRouterParam('action');
    }

    public function getUrl() {
        $this->_protocol = $this->_detectRequestProtocol();
        return $this->_protocol . ':' . $this->_siteUrl;
    }

    public function getCurrentUrl() {
        return  $this->_routerParams['url'];
    }

    public function getPublic() {
        return $this->_public;
    }
    
    public function getAssetsUrl() {
        return $this->getUrl().$this->_public.$this->_assets;
    }
    
    public function getAssetsPath() {
        return $this->getPath().$this->_public.$this->_assets;
    }   
    
    public function getThemeUrl() {
        if ($this->_site instanceof OPUS_SITE_Site) {
            return rtrim($this->getUrl(), '/') . '/_site/' . rawurlencode($this->_site->getId());
        }
        return $this->getUrl().$this->_public.'themes/'.$this->config->get('theme');
    }
    
    public function getThemePath() {
        if ($this->_site instanceof OPUS_SITE_Site) {
            return rtrim($this->_site->getPublicPath(), '/\\');
        }
        return $this->getPath().$this->_public.'themes/'.$this->config->get('theme');
    }       
    
    
    public function getPath() {
        return $this->_sitePath;
    }
    
    public function error_404($url, $params) {
        echo "<h1>DISPATCH erreur 404 : $url</h1>";
        echo "<pre>PARAMS " . print_r($params, true) . "</pre>";
        exit();
    }

    public static function output_handler($buffer, $flags) {
        static $input = array();

        if ($flags & PHP_OUTPUT_HANDLER_START)
            $flags_sent[] = "PHP_OUTPUT_HANDLER_START";
        if ($flags & PHP_OUTPUT_HANDLER_CONT)
            $flags_sent[] = "PHP_OUTPUT_HANDLER_CONT";
        if ($flags & PHP_OUTPUT_HANDLER_END)
            $flags_sent[] = "PHP_OUTPUT_HANDLER_END";
        $flags_sent = array();
        $input[] = implode(' | ', $flags_sent) . " ($flags): $buffer<br />";

        self::$_output .= $buffer;

        if ($flags & PHP_OUTPUT_HANDLER_END) {
//           foreach($input as $k => $v) self::$_output .= "$k: $v";
        }

        return self::$_output;
    }

    protected function processUrl() {
//echo "<pre>PROCESSURL " . print_r($this->_routerParams, true) . "</pre>";
        $this->_routeDebugLog('process-url-start', array(
            'routerParams' => $this->_routerParams,
        ));

        if (!isset($this->_routerParams['module'], $this->_routerParams['controller'], $this->_routerParams['action'])) {
            $this->_routeDebugLog('process-url-missing-target', array(
                'routerParams' => $this->_routerParams,
            ));
            return false;
        }
        $this->_routerParams['url'] = $this->getUrl();

        $controllerName = ucfirst((string)$this->_routerParams['controller']) . '_controller.class.php';
        $packagePath = ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getPackagePath() : '';
        $controller_path = rtrim($packagePath, '/\\') . '/controllers/' . $controllerName;
        $controller_path = str_replace('\\', '/', $controller_path);
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " PATH " . $controller_path, __FILE__, __LINE__, TODO);
//echo "<br> PATH: $controller_path" ; 
//echo "<br> URL: ".$this->_routerParams['url'] ; 

        if (!file_exists($controller_path)) {
            $this->_routeDebugLog('process-url-controller-missing', array(
                'controllerPath' => $controller_path,
                'routerParams' => $this->_routerParams,
            ));
            return false;
        }

        $this->_routeDebugLog('process-url-controller-ok', array(
            'controllerPath' => $controller_path,
            'routerParams' => $this->_routerParams,
        ));
        return $controller_path;
    }

    //////////////////////////////
    ///////// METHODS ////////////
    //////////////////////////////

    public function error_proc($signal) {
        $msg = __CLASS__ . "::" . __FUNCTION__ . "  Error in processing transition[$signal," . $this->getCurrentState() . "]";
        die("<h1>$msg</h1>");
    }


    public function dispatch() {
        
        // serveur off ?
        
        // user hang ?
        
        
//        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " signal: $signal ", __FILE__, __LINE__, APP_COLOR);
        $this->_protocol = $this->_detectRequestProtocol();
        $this->_https = $this->_protocol === 'https';

        $this->_routerParams = $this->router->execute();
        $this->_routeDebugLog('router-execute-result', array(
            'routerParams' => $this->_routerParams,
        ));
//echo "<font color='blue'><pre>AFTER EXECUTE " . print_r($this->_routerParams, true) . "</pre></font>";

        $controller_path = $this->processUrl();
//        OPUS_Debug::addDump(__CLASS__ . "::" . __FUNCTION__ . " ROUTER RESULT ", $this->_routerParams, __FILE__, __LINE__, 'red');

        if ($controller_path == false) {
//            OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " ROUTE FOUND " . ($this->_routerParams['found'] ? "YES" : "No!!!!!"), __FILE__, __LINE__, TODO);
            $this->_routeDebugLog('dispatch-404', array(
                'requestUri' => $_SERVER['REQUEST_URI'] ?? '',
                'routerParams' => $this->_routerParams,
            ));
            $this->error_404($_SERVER['REQUEST_URI'], $this->_routerParams);
        } else {
            $this->_module = $this->getSiteId() ?: (string)$this->_routerParams['module'];
            $this->_modulePath = ($this->_site instanceof OPUS_SITE_Site) ? $this->_site->getPackagePath() : (ROOT . '/application/' . $this->_module);
            $controller = $this->_routerParams['controller'];
            $this->_routerParams['module_path'] = rtrim($this->_modulePath, '/\\') . '/';
            $this->_routerParams['site_package_path'] = $this->_routerParams['module_path'];
            $this->_routerParams['controller_path'] = $controller_path;
            require_once($controller_path);
            $class = ucfirst($controller) . '_controller';
            $this->_controllerClass = $class;
            $ctrl = $this->create_controller_instance($class, array($this->_routerParams));
            $this->response = $ctrl->run();
        }
    }



}

// class
?>
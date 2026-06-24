<?php

#[AllowDynamicProperties]
/**
 * Legacy route configuration value object.
 *
 * Carries route configuration fields used by legacy OPUS configuration parsing.
 */
class Route_Object {

    public $id = '';
    public $menu = '';
    public $method = 'layout';
    public $rule = '/';
    public $target = array();
    public $conditions = array();

    public function __construct($id, $rule, $target=array(), $conditions=array(), $menu='', $method='layout') {
        $this->id = $id;
        $this->rule = $rule;
        $this->target = $target;
        $this->conditions = $conditions;
        $this->menu = $menu;
        $this->method = $method;
                        
        return $this;
    }

}

#[AllowDynamicProperties]
/**
 * Legacy OPUS configuration container.
 *
 * Stores and exposes application, routing and runtime configuration loaded from OPUS configuration sources.
 */
class OPUS_Configuration {

    protected $_env = null;
    protected $_configArray = array();
    protected $_environments = array();
    protected $_routes = array();

    private function _merge($arrSrc, &$arrDest) {
        foreach ($arrSrc as $k => $v) {
            if (is_array($v)) {
                $this->_merge($v, $arrDest[$k]);
            } else {
                $arrDest[$k] = $v;
            }
        }
    }

    protected function findEnv() {
        if (defined('OPUS_ENV') && isset($this->_environments[OPUS_ENV])) {
            $this->_env = OPUS_ENV;
            return;
        }

        $requestHosts = array(
            $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '',
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['SERVER_NAME'] ?? '',
        );
        $normalizedRequestHosts = array();
        foreach ($requestHosts as $requestHost) {
            $host = trim((string)$requestHost);
            if ($host === '') {
                continue;
            }
            if (strpos($host, ',') !== false) {
                $parts = explode(',', $host);
                $host = trim($parts[0]);
            }
            $normalizedRequestHosts[] = strtolower($this->_normalizeHost($host));
        }

        foreach ($this->_environments as $environment => $arr) {
            $configuredHost = strtolower($this->_normalizeHost((string)($arr['siteUrl'] ?? '')));
            if ($configuredHost !== '' && in_array($configuredHost, $normalizedRequestHosts, true)) {
                $this->_env = $environment;
                return;
            }
        }

        // Modern OPUS demo: when siteUrl is {AUTO}/empty/local, the same codebase
        // can run from localhost, a LAN host, or a Cloudflare/HTTPS public host.
        // If no explicit environment matches, dev is the safe default.
        if (isset($this->_environments['dev'])) {
            $this->_env = 'dev';
            return;
        }

        $keys = array_keys($this->_environments);
        if (!empty($keys)) {
            $this->_env = $keys[0];
            return;
        }

        throw new Exception("Environment not found !");
    }

    protected function _normalizeHost($siteUrl): string {
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
    
    protected function _extends() {

        foreach ($this->_configArray['environments'] as $environment => $arr) {
            $keys = array_keys($arr);
            if (substr($keys[0], 0, 9) == "extends::") {
                $extends = substr($keys[0], 9, strlen($keys[0]) - 9);
                $this->_environments[$environment] = $this->_environments[$extends];
                $this->_merge($arr[$keys[0]], $this->_environments[$environment]);
            } else {
                $this->_environments[$environment] = $arr;
            }
        }    
         unset($this->_configArray['environments']);    
         $this->findEnv();    
    }

    public function get($key) {
        return $this->_configArray[$key] ?? null;
    }

    public function set($key, $value) {
        $this->_configArray[$key] = $value;
    }

    public function getEnv($key) {
        $env = $this->_env;
        return $this->_environments[$env][$key] ?? null;
    }

    public function setEnv($key, $value) {
        $env = $this->_env;
        $this->_environments[$env][$key] = $value;
    }

    public function getDatabase($id) {
        $env = $this->_env;
        return $this->_environments[$env]['database'][$id];
    }


    public function getRoutes() {
        $routes = $this->_configArray['routes'];
//       echo "<hr><font color='orange'> CONFIG getRoutes <pre>". print_r($routes, true) ."</pre></font>";

        foreach ($routes as $id => $route) {
//            echo "<hr>CONFIG getRoutes <hr>$id <pre>" . print_r($route, true) . "</pre><hr>";
            if(!isset($route['conditions'])) $route['conditions'] = array();
            $this->_routes[$id] = new Route_Object($id, $route['rule'], $route['target'], $route['conditions'], $route['menu'], $route['method']);
        }
//        echo "<br><font color='purple'> CONFIG getRoutes <pre>" . print_r($this->_routes, true) . "</pre></font><";
        return $this->_routes;
    }

protected function _detect_os(){
    $a=$_SERVER['HTTP_USER_AGENT'];
    if (preg_match('#windows\snt\s5\.1#i',$a)) return('Microsoft Windows XP');
    if (preg_match('#linux\sx86_64#i',$a)) return('Linux (64 bits)');
    if (preg_match('#khtml#i',$a)) return('Linux');
    if (preg_match('#linux#i',$a))return('Linux');
    if (preg_match('#libwww-fm#i',$a))return('Linux');
    if (preg_match('#freebsd#i',$a))return('FreeBSD');
    if (preg_match('#mac\sos\sx#i',$a))return('Mac OS X');
    if (preg_match('#windows\snt\s6\.1#i',$a))return('Microsoft Windows 7');
    if (preg_match('#haiku#i',$a))return('Haiku');
    if (preg_match('#windows\snt\s6\.0;\swow64#i',$a))return('Microsoft Windows Vista (64bits)');
    if (preg_match('#windows\snt\s6\.0;\swin64#i',$a))return('Microsoft Windows Vista (64bits)');
    if (preg_match('#windows\snt\s6\.0#i',$a))return('Microsoft Windows Vista');
    if (preg_match('#sunos#i',$a))return('Open Solaris');
    if (preg_match('#android#i',$a))return('Android');
    if (preg_match('#windows\s95#i',$a))return('Microsoft Windows 95');
    if (preg_match('#windows\snt\s5\.0#i',$a))return('Microsoft Windows 2000');
    if (preg_match('#windows\snt\s5\.3#i',$a))return('Microsoft Windows Server 2003');
    if (preg_match('#windows\snt#i',$a))return('Microsoft Windows NT');
    if (preg_match('#windows\s98#i',$a))return('Microsoft Windows 98');
    if (preg_match('#windows\sce#i',$a))return('Microsoft Windows Mobile');
    if (preg_match('#windows\sphone\sos[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Microsoft Windows Phone version '.$c[1]);
    if (preg_match('#mac_powerpc#i',$a))return('Mac OS X');if (preg_match('#macintosh#i',$a))return('Macintosh');
    if (preg_match('#cygwin_nt#i',$a))return('Microsoft Windows 2000');if (preg_match('#os\/2#i',$a))return('Microsoft OS/2');
    if (preg_match('#symbianos[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Symbian OS version '.$c[1]);
    if (preg_match('#symbian-crystal[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('Symbian OS version '.$c[1]);
    if (preg_match('#offbyone;\swindows\s2000#i',$a))return('Microsoft Windows XP');
    if (preg_match('#windows\s2000#i',$a))return('Microsoft Windows 2000');
    if (preg_match('#nintendo\swii#i',$a))return('Nintendo Wii');
    if (preg_match('#playstation\sportable#i',$a))return('PlayStation Portable');
    if (preg_match('#iphone\sos\s[\s\/]([0-9v]{1,7}(?:[\._][0-9a-z]{1,7}){0,7})#i',$a,$c))return('iPhone OS version '.$c[1]);
    return 'OS non identifié';
}       

public function get_os() {
    return $this->_os;
}

protected function _detect_browser(){
    $a=$_SERVER['HTTP_USER_AGENT'];$b='[\s\/]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})';
    if (preg_match('#firefox'.$b.'#i',$a,$c))return('Firefox version '.$c[1]);
    if (preg_match('#msie'.$b.'#i',$a,$c))return('Microsoft Internet Explorer version '.$c[1]);
    if (preg_match('#chrome'.$b.'#i',$a,$c))return('Google Chrome version '.$c[1]);
    if (preg_match('#icab'.$b.'#i',$a,$c))return('iCab (Crystal Atari Browser) version '.$c[1]);
    if (preg_match('#microsoft\sPocket\sinternet\sexplorer'.$b.'#i',$a,$c))return('Microsoft Pocket Internet Explorer version '.$c[1]);
    if (preg_match('#mspie'.$b.'#i',$a,$c))return('Microsoft Pocket Internet Explorer version '.$c[1]);
    if (preg_match('#konqueror'.$b.'#i',$a,$c))return('Konqueror version '.$c[1]);
    if (preg_match('#lunascape'.$b.'#i',$a,$c))return('Lunascape version '.$c[1]);
    if (preg_match('#lynx'.$b.'#i',$a,$c))return('Lynx version '.$c[1]);
    if (preg_match('#minimo'.$b.'#i',$a,$c))return('Minimo version '.$c[1]);
    if (preg_match('#netscape'.$b.'#i',$a,$c))return('Netscape version '.$c[1]);
    if (preg_match('#^nokia([^\/]+)\/([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c)) return('Nokia '.trim($c[1]).' version '.$c[2]);
    if (preg_match('#offbyone;#i',$a))return('OffByOne');if (preg_match('#omniweb'.$b.'#i',$a,$c))return('Omniweb version '.$c[1]);
    if (preg_match('#opera'.$b.'#i',$a,$c))return('Opera version '.$c[1]);if (preg_match('#safari'.$b.'#i',$a,$c))return('Safari version '.$c[1]);
    if (preg_match('#seamonkey'.$b.'#i',$a,$c))return('SeaMonkey version '.$c[1]);
    if (preg_match('#w3m'.$b.'#i',$a,$c))return('W3m version '.$c[1]);
    if (preg_match('#ia_archiver#i',$a))return('Alexa Bot');
    if (preg_match('#ask\sjeeves#i',$a))return('Ask Jeeves Bot');
    if (preg_match('#curl'.$b.'#i',$a,$c))return('Curl version '.$c[1]);
    if (preg_match('#exabot'.$b.'#i',$a,$c))return('Exaled bot version '.$c[1]);
    if (preg_match('#ng'.$b.'#i',$a,$c))return('Exaled bot version '.$c[1]);
    if (preg_match('#exabot-thumbnails#i',$a))return('Exaled bot');
    if (preg_match('#gamespyhttp'.$b.'#i',$a,$c))return('GameSpy Industries bot version '.$c[1]);
    if (preg_match('#gigabot'.$b.'#i',$a,$c))return('Gigablast bot version '.$c[1]);
    if (preg_match('#googlebot'.$b.'#i',$a,$c))return('Google bot version '.$c[1]);
    if (preg_match('#googlebot-image'.$b.'#i',$a,$c))return('Google bot (image) version '.$c[1]);
    if (preg_match('#grub-client[\s\/\-]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('LookSmart Grub bot version '.$c[1]);
    if (preg_match('#yahoo! slurp#i',$a))return('Yahoo! Search) bot');
    if (preg_match('#slurp#i',$a))return('Inktomi Slurp bot');
    if (preg_match('#msnbot'.$b.'#i',$a,$c))return('Microsoft MSN Search bot version '.$c[1]);
    if (preg_match('#scooter[\s\/\-]([0-9v]{1,7}(?:\.[0-9a-z]{1,7}){0,7})#i',$a,$c))return('AltaVista Scooter bot version '.$c[1]);
    if (preg_match('#wget'.$b.'#i',$a,$c))return('Wget bot version '.$c[1]);
    if (preg_match('#w3c_validator'.$b.'#i',$a,$c))return('W3C validator bot version '.$c[1]);
    return 'Navigateur non identifié';
    }

public function get_browser() {
    return $this->_browser;
}
    
    
    
    
}

?>
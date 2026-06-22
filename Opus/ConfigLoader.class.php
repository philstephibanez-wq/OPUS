<?php

#[AllowDynamicProperties]
class ASAP_ConfigLoader {
    protected ?string $_env = null;
    protected string $_filename = '';
    protected $_xml = null;
    protected array $_confVars = array();
    protected string $_php_content = '';
    public string $phpFile = '';

    private function __construct($xmlFile) {
        $this->_filename = $xmlFile;
        $phpFile = $xmlFile . '.php';

        if (!file_exists($xmlFile)) {
            throw new ASAP_Exception('Config XML file not found: ' . $xmlFile);
        }

        $configFileLastMod = filemtime($xmlFile);
        $targetFileLastMod = @filemtime($phpFile) ?: 0;
        $mustRebuild = ($configFileLastMod > $targetFileLastMod || !is_file($phpFile));

        if (!$mustRebuild && defined('ROOT')) {
            $cached = @file_get_contents($phpFile);
            if (is_string($cached) && preg_match("/\['rootPath'\] = '([^']*)';/", $cached, $m)) {
                $cachedRoot = rtrim(str_replace('\\', '/', $m[1]), '/') . '/';
                $currentRoot = rtrim(str_replace('\\', '/', ROOT), '/') . '/';
                if ($cachedRoot !== $currentRoot) {
                    $mustRebuild = true;
                }
            }
        }

        if ($mustRebuild) {
            if (!class_exists('SimpleXMLElement')) {
                throw new ASAP_Exception('PHP extension simplexml/xml is required to load ASAP XML configuration. Enable it in php.ini.');
            }
            $contents = file_get_contents($xmlFile);
            if ($contents === false) {
                throw new ASAP_Exception('Cannot read config XML file: ' . $xmlFile);
            }
            $this->_xml = new SimpleXMLElement($contents);
            $this->_parseXml($this->_xml);
            $this->_generatePhpConfig();
            $this->_writePhpFile();
        }
        $this->phpFile = $phpFile;
    }

    public static function getConfig($xmlFile) {
        $thisObj = new ASAP_ConfigLoader($xmlFile);
        return $thisObj->phpFile;
    }

    private function _writePhpFile(): void {
        $tempFilePath = $this->_filename . '.tmp';
        if (file_put_contents($tempFilePath, $this->_php_content, LOCK_EX) === false) {
            throw new ASAP_Exception('Cannot write temporary config PHP file: ' . $tempFilePath);
        }
        rename($tempFilePath, $this->_filename . '.php');
    }

    private function _parseXml($xmlNodes, $path = array(), $level = 0): void {
        foreach ($xmlNodes as $tag => $xml) {
            $currentPath = $path;
            if ($tag !== 'item' && $tag !== 'route') {
                $currentPath[] = (string)$tag;
            }

            $currentType = 'string';
            foreach ($xml->attributes() as $name => $value) {
                $name = (string)$name;
                $value = (string)$value;
                switch ($name) {
                    case 'id':
                        $currentPath[] = $value;
                        break;
                    case 'extends':
                        $currentPath[] = 'extends::' . $value;
                        break;
                    case 'type':
                        $currentType = $value;
                        break;
                }
            }

            if (count($xml->children()) > 0) {
                if ($level > 30) {
                    throw new ASAP_Exception('Too much nested tags in config XML file');
                }
                $this->_parseXml($xml->children(), $currentPath, $level + 1);
                continue;
            }

            $value = $this->_castValue((string)$xml, $currentType);
            $this->_confVars[] = array($currentPath, $value);
        }
    }

    private function _castValue(string $value, string $type) {
        $value = $this->_resolvePlaceholders($value);
        switch (strtolower($type)) {
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'array':
                return array();
            case 'string':
            default:
                return $value;
        }
    }

    private function _resolvePlaceholders(string $value): string {
        if ($value === '{ROOT}' && defined('ROOT')) {
            return rtrim(str_replace('\\', '/', ROOT), '/') . '/';
        }
        return preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function ($m) {
            $env = getenv($m[1]);
            return $env !== false ? $env : '';
        }, $value);
    }

    protected function _generatePhpConfig(): void {
        $php = "<?php\n";
        $php .= "class Config extends ASAP_Configuration {\n";
        $php .= "    public function __construct() {\n";
        foreach ($this->_confVars as [$path, $value]) {
            if (!$path) {
                continue;
            }
            $php .= '        $this->_configArray';
            foreach ($path as $part) {
                $php .= '[' . var_export($part, true) . ']';
            }
            $php .= ' = ' . var_export($value, true) . ";\n";
        }
        $php .= "        \$this->_extends();\n";
        $php .= "    }\n";
        $php .= "}\n";
        $this->_php_content = $php;
    }
}

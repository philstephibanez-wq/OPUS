<?php

#[AllowDynamicProperties]
/**
 * OPUS I18N service.
 *
 * Loads and resolves translations for OPUS applications.
 */
class OPUS_I18N_I18n  implements OPUS_I18N_I18nInterface {

    protected static $_instance = null;
    protected $_app = null;
    protected $_controller;
    protected $_pagePath = '';
    protected $_sharedPath = '';
    protected $_code;     // current language code ex: fr_FR
    protected $_availableLanguages = array();     // Array og all local subdirectories found
    protected $_sharedPathLang = array();
    protected $_pagePathLang = array();
    protected $_dic = array();          // Array of dictionaries
    protected $_local = array();         // Array of locales (l10n)
    protected $_cases = array("NS", "NP", "NP1", "MS", "MP", "MP1", "FS", "FP", "FP1");

    // CONSTRUCTOR
    private function __construct($lang = null) {
        $this->_app = OPUS_Application::getInstance();

        $this->_code = $lang;
        $this->_controller = $this->_controller = OPUS_Controller::getInstance();
        if ($this->_controller != null)
            $this->_pagePath = $this->_controller->getParam('page_path') . "local/";

        $this->_sharedPath = $this->_app->getPath() . "application/default/local/";
// die($this->_sharedPath)   ;
        // get available languages in local directory
        // load _availableLanguages as key=country value lang_country
        // like this: ('us' => 'en_US') from local/en_US
        $this->getAvalaibleLanguages();
//		\Opus\Diagnostics\Diagnostics::dump(__CLASS__.__FUNCTION__."   ",$this->_availableLanguages, __FILE__, __LINE__, 'blue');
//		\Opus\Diagnostics\Diagnostics::dump(__CLASS__.__FUNCTION__." SHARED  ",$this->_sharedPathLang, __FILE__, __LINE__, 'blue');
//		\Opus\Diagnostics\Diagnostics::dump(__CLASS__.__FUNCTION__."  PAGE ",$this->_pagePathLang, __FILE__, __LINE__, 'blue');

        if ($lang == null) {
            $this->setLanguage();
        } else
            $this->_code = 'FR-fr';

        return $this;
    }

    public static function getInstance($lang = null, $controller = null) {
        if (OPUS_I18N_i18n::$_instance != null) {
            return OPUS_I18N_i18n::$_instance;
        } else {
            OPUS_I18N_i18n::$_instance = new OPUS_I18N_i18n($lang, $controller);
            return OPUS_I18N_i18n::$_instance;
        }
    }


    static function getPlural($count) {
        if (function_exists('plural')) {
            return plural($count);
        } else {
            if ($count < 2)
                $p = "S"; else
                $p = "P";
            return $p;
        }
    }

    function setLanguage($code = null) {
        if ($code == null)
            $code = $this->getHTTPAcceptLanguage();

        if (in_array($code, $this->_availableLanguages)) {
            $this->_code = $code;
            if (!$this->_loadSharedDictionary())
                return false;
        } else {
            throw new Exception("This language $code is not available");
            return false;
        }
        return true;
    }

    public function getAvalaibleLanguages() {
        if (!is_dir($this->_sharedPath)) {
            throw new OPUS_Exception('I18N shared local directory not found: ' . $this->_sharedPath);
        }
        $iterator = new DirectoryIterator($this->_sharedPath);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (!in_array($fileinfo->getFilename(), array('.', '..'))) {
                    $this->_availableLanguages[$fileinfo->getFilename()] = $fileinfo->getFilename();
                    $this->_sharedPathLang[$fileinfo->getFilename()] = $this->_sharedPath . $fileinfo->getFilename() . "/";
                }
            }
        }
//        echo "<br> I18N_pagePath   ".$this->_pagePath; die();
        if($this->_pagePath != '' && is_dir($this->_pagePath)) {
            $iterator = new DirectoryIterator($this->_pagePath);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isDir()) {
                    if (!in_array($fileinfo->getFilename(), array('.', '..'))) {
                        $this->_availableLanguages[$fileinfo->getFilename()] = $fileinfo->getFilename();
                        $this->_pagePathLang[$fileinfo->getFilename()] = $this->_pagePath . $fileinfo->getFilename() . "/";
                    }
                }
            }
        }
    }


    function getLanguage() {
        return $this->_code;
    }

    private function _addPlugin($filePath) {
        $filename = $filePath . "/plugin.inc.php";
        if (file_exists($filename)) {
            include($filename);
        }
    }

    //fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3
    private function getHTTPAcceptLanguage() {
        $langs = explode(';', strtolower(($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "fr")));
        $locales = $this->_availableLanguages;
        foreach ($langs as $value_and_quality) {
            // Loop through all the languages, to see if any match our supported ones
            $values = explode(',', $value_and_quality);
            foreach ($values as $value) {
                if (in_array($value, $locales)) {
                    // If found, return the language
                    return $value;
                }
            }
        }
        // If we can't find a supported language, we use the default
        return 'FR-fr';
    }

    private function _loadSharedDictionary() {
//		\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__.$this->_code, __FILE__, __LINE__, 'red');
//		\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__.$this->_sharedPathLang[$this->_code], __FILE__, __LINE__, 'red');
        $iterator = new DirectoryIterator($this->_sharedPathLang[$this->_code]);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                if ($fileinfo->getExtension() == 'xml') {
                    $this->_loadDictionary($this->_sharedPathLang[$this->_code] . $fileinfo->getFilename());
                    $this->_addPlugin($this->_sharedPathLang[$this->_code] . $fileinfo->getFilename());
                }
            }
        }
    }

    private function _loadDictionary($filePath) {
//		\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   ".$filePath, __FILE__, __LINE__, 'white');
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
            ;
            return false;
        }
        $handle = fopen($filePath, "r");
        $contents = fread($handle, filesize($filePath));
        fclose($handle);
        $this->_parseDictionary($contents); // OVERRIDE exixting key
        return true;
    }

    public function loadDictionary($filename) {
//		\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   ".$filename, __FILE__, __LINE__, 'blue');
        foreach ($this->_pagePathLang as $path) {
//			\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   ".$path, __FILE__, __LINE__, 'red');
            $iterator = new DirectoryIterator($path);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    if ($fileinfo->getExtension() == 'xml') {
                        $this->_loadDictionary($path . $fileinfo->getFilename());
//						\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   ".$path.$fileinfo->getFilename(), __FILE__, __LINE__, 'red');
                    }
                }
            }
        }
        return true;
    }

    private function _parseDictionary($str) {
        try {
            $xml = new OPUS_SimpleXMLElementExtended($str);
        } catch (Exception $e) {
            throw new Exception($str);
        }

        for ($s = 0; $s < count($xml->string); $s++) {
            if (!in_array($xml->string[$s]->getAttribute("gender"), $this->_cases)) {
                $gender = "NS";
            } else {
                $gender = (string) $xml->string[$s]->getAttribute("gender");
            }
            $key = (string) $xml->string[$s]->getAttribute("name");
            $key .= '::' . $gender;
            $this->_dic[$key] = (string) $xml->string[$s];
        }
        return true;
    }

// end parseDistionary

    public function translate($needle, $count = 1, $gender = "N", $noError=false) {
        $search = "$needle::$gender";
//\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   $search, $gender, $count ", __FILE__, __LINE__, 'red');
        $plural = OPUS_I18N_i18n::getPlural($count);
        $name = $search . $plural;

//\Opus\Diagnostics\Diagnostics::debug(__CLASS__.__FUNCTION__."   $name, $str ", __FILE__, __LINE__, 'red');
        if (isset($this->_dic[$name])) {
            $str = $this->_dic[$name];
        } else {
            if($noError) {
               return $needle;
            } else {
               return "[translate:" . $name . ", $count]";
            }

        }
        if (preg_match('/%c/', $str)) {
            $str = preg_replace('/%c/', (string) $count, $str);
        }

        return $str;
    }

    public function getDictionary() {
        return $this->_dic;
    }

}

// end class
?>
<?php

#[AllowDynamicProperties]
class ASAP_VIEW_Html {
	protected $_app = null;
	protected $_i18n = null;
	protected $_controller=null;
	private   $_output = '';
	protected $_encode = "utf-8";
	protected $_styleSheets = array();
	protected $_scripts = array();
	protected $_metas = array();
	protected $_theme = '';
	
	function __construct() {
		$this->_app = ASAP_Application::getInstance();	
		$this->_controller = ASAP_Controller::getInstance();		
		$theme = $this->_app->config->get('theme');
		$this->applyTheme($theme);	
		
		$this->_i18n = ASAP_I18N_I18n::getInstance(null);	
//		ASAP_Debug::addDump(__CLASS__.__FUNCTION__."   ",$this->_i18n->getDictionary(), __FILE__, __LINE__, 'blue');
				
		$this->init();
	}	
//
	
	protected function init() {
//		throw new ASAP_Exception(VIEW CLASS (to override));
//		echo "<h1><font color='BLUE'>VIEW CLASS (to override)</font></h1>";
	}	
	
	public function addModuleScript($script) {
	    $url  = $this->_app->getUrl()."application/";
	    $url .= $this->_app->getModule()."/javascript/";
	    $this->_scripts[] =  $url . $script;		
	}
	
	
	public function require_script($script) { // ie: photos_photo.js or photos/photo.js
            $script = str_replace("_", "/", $script);
            list($module, $filename) = explode("/", $script);
	    
            $url  = $this->_app->getUrl()."application/";
	    $url .= $module."/javascript/".$filename;;

            $this->_scripts[] =  $url;
	}
	
	private function _addAllCss() {			
	    $filenames = array();
	    $path = $this->_app->getThemePath()."/css";
	    if (!is_dir($path)) { return; }
	    $iterator = new DirectoryIterator($path);
	    foreach ($iterator as $fileinfo) {
	        if ($fileinfo->isFile()) {
	        	if( strtolower($fileinfo->getExtension()) == 'css')
	        	$this->addStyleSheet($fileinfo->getFilename());
	        }
	    }		
	}	
	
	protected function _getHeader(){
		$this->_addAllCss();
		$header  = '';	
		$header .= '<!doctype html>';
		$header .= '<html lang="fr">';
		$header .= '   <head>';
		$header .= '      <meta charset="'.$this->_encode.'" />';
		$header .= '      <meta name="viewport" content="width=device-width, initial-scale=1" />';
		$header .= '      <title>'.$this->getTitle().'</title>';
		
  		if(count($this->_metas) > 0){
			foreach($this->_metas as $name => $content) {
				$header .= '      <meta name="'.$name.'" content="'.$content.'" />';			
			}
		}	
		
		if(count($this->_styleSheets) > 0){
			foreach($this->_styleSheets as $num => $sheet) {
				$href = rtrim($this->_app->getThemeUrl(), '/') . '/css/' . ltrim($sheet, '/');
				$header .= '<link rel="stylesheet" href="'.$href.'" type="text/css" media="all" />';
			}
		}
		
		if(count($this->_scripts) > 0){
			foreach($this->_scripts as $num => $script) {
				$header .= '<script language="JavaScript" type="text/javascript" src="'.$script.'"></script>';
			}
		}		
    
 		$header .= '  </head>';	
 		
		return $header;
	}
			
   final public function __call($methodName, $args) {
        if (preg_match('~^(new|set|get)([A-Z])(.*)$~', $methodName, $matches)) {
        	$function = $matches[1];
            $property = strtolower($matches[2]) . $matches[3]; 
            if($this->_controller) {
	            if (!property_exists($this->_controller, $property) and $function != 'new') {
	                throw new Exception('Property ' . $property . ' not exists');
	            }            	
            }  else {
	            if (!property_exists($this, $property) and $function != 'new') {
	                throw new Exception('Property ' . $property . ' not exists');
	            }           	
            }        
            switch($function) {
            	case 'new':
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property);
                case 'default':
                    throw new Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    final protected function get($property) {
       if($this->_controller) {
    	return $this->_controller->$property;
       } else {
    	return $this->$property;       	
       }
    }

    final protected function set($property, $value) {
      if($this->_controller) {
    	$this->_controller->$property = $value;
      } else {
    	$this->$property = $value;     	
      }
        return $this;
    }

    final protected function checkArguments(array $args, $min, $max, $methodName) {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }   	
	
	public function add($output) {
		$this->_output .= $output;
	}
	
	public function applyTheme($theme='default') {
		$this->_theme = $theme;
	}
	
	public function currentTheme() {
		return $this->_theme;
	}
	
	public function addStyleSheet($sheet) {
		$this->_styleSheets[] = $sheet;
	}
	
	public function addMeta($meta) {
		$this->_metas[] = $meta;
	}
	
	public function addScript($script) {
		$this->_scripts[] = $script;
	}	
		
//	public function setTheme($theme){
//		$this->newTheme($this->_controller->getUrl()."www/themes/".$theme);
//	}
	
	public function setEncoding($encode){
		$this->_encode = $encode;
	}
	
	
	
    public function draw() {
    	$old_buffer = ob_get_contents();
    	ob_end_clean();
		
  		echo $this->_getHeader();
 		echo "<body>";
		echo  $this->_output;
                echo ASAP_Debug::get();
                if($this->_app->config->getEnv("debug")) echo "<div class='echo'>$old_buffer</div>"; // recupere les warnings etc... PHP
 		echo "</body>";
 		echo "</html>";
 		
   }

}

?>
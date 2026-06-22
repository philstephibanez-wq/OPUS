<?php

//autogetter/setter pour les variables protected ou public
define('HELPER_COLOR', 'blue');

#[AllowDynamicProperties]
class ASAP_HELPER_Helper {
	protected $_app = null;
        protected $_controller = null;
 	protected $_i18n = null;
	
	function __construct() {
		$this->_app = ASAP_Application::getInstance();
		$this->_i18n = ASAP_I18N_I18n::getInstance(null, $controller);	
 			
		$this->_controller = ASAP_Controller::getInstance();	
			
		$this->init();
	}	

    protected function init() {}
	
    
}

?>
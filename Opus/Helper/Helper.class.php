<?php

//autogetter/setter pour les variables protected ou public
define('HELPER_COLOR', 'blue');

#[AllowDynamicProperties]
/**
 * OPUS helper base class.
 *
 * Provides shared helper utilities consumed by OPUS runtime components.
 */
class OPUS_HELPER_Helper {
	protected $_app = null;
        protected $_controller = null;
 	protected $_i18n = null;
	
	function __construct() {
		$this->_app = OPUS_Application::getInstance();
		$this->_i18n = OPUS_I18N_I18n::getInstance(null, $controller);	
 			
		$this->_controller = OPUS_Controller::getInstance();	
			
		$this->init();
	}	

    protected function init() {}
	
    
}

?>
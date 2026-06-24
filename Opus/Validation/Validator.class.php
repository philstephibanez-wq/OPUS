<?php

/**
 * Validator class
 * Check fields and data validity
 * @category classes
 *
 * @author Steph. IBANEZ <support@logandplay.com>
 * @copyright PrestaShop
 * @license opn
 * @version 1.0
 *
 */

#[AllowDynamicProperties]
/**
 * OPUS validator.
 *
 * Provides validation helpers used by OPUS forms and data-processing code.
 */
class OPUS_Validator {
	protected $_valid = true;
	protected $_messages = array();
	protected $_toValidate = null;
	protected $_label = '';
	protected $_i18n = '';

	
	public function __construct($label, $toValidate, $controller, $args=null) { 
            $this->_label = $label;
            $this->_toValidate = $toValidate ;   
            $this->_i18n = OPUS_I18N_I18n::getInstance(null, $this);	
 
        }
	
	/**
	*
	* @return 5this object to chain validations
	*/	
	protected function setResult($function, $result) { 
		if($result === false) $this->_messages[] = $this->_i18n->translate($this->_label). ' NOT '.$function;
		$this->_valid =  $result;
		return $this; // for chaining validations
	}
	
	public function isValide() { return $this->_valid; }

	public function getMessages() { return $this->_messages; }

	public function is_true() {
		$result = ($this->_toValidate === true) ? true : false;		
		return $this->setResult(__FUNCTION__, $result);
	}

        public function isBoolean() {
            if (filter_var($this->_toValidate, FILTER_VALIDATE_BOOLEAN)!== false) {
                $result = true;;
             } else {
                $result = false;;
            }
 	  return $this->setResult(__FUNCTION__, $result);         
        }
        
	public function is_false() {   
            $this->isBoolean();
		$result = ($this->_toValidate === false) ? true : false;               
		return $this->setResult(__FUNCTION__, $result);
	}


	/**
	 * Check for e-mail validity
	 */
	public function isEmail() {
		$result = preg_match('/^[a-z0-9!#$%&\'*+\/=?^`{}|~_-]+[.a-z0-9!#$%&\'*+\/=?^`{}|~_-]*@[a-z0-9]+[._a-z0-9-]*\.[a-z0-9]+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for MD5 string validity
	 */
	public function isMd5() {
		$result = (boolean) preg_match('/^[a-z0-9]{32}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for SHA1 string validity
	 */
	public function isSha1()
	{
		$result = (boolean) preg_match('/^[a-z0-9]{40}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for a float number validity
	 */
	public function isFloat() {
		$result = strval(floatval($this->_toValidate)) == strval($this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	public function isUnsignedFloat(){
		$result = strval(floatval($this->_toValidate)) == strval($this->_toValidate) AND $this->_toValidate >= 0;
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for a float number validity
	 */
	public function isOptFloat() {
		$result = empty($this->_toValidate) OR $this->isFloat($this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}


	/**
	 * Check for name validity
	 */
	public function isName() {
		$result = (boolean) preg_match('/^[^0-9!<>,;?=+()@#"�{}_$%:]*$/ui', stripslashes($this->_toValidate));
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for sender name validity
	 */
	public function isMailName() {
		$result = (boolean) preg_match('/^[^<>;=#{}]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for e-mail subject validity
	 */
	public function isMailSubject()
	{
		$result = (boolean) preg_match('/^[^<>;{}]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for image file validity
	 */
	public function isImgFile() {
		$result = (boolean) preg_match('/^[a-z0-9_-]+\.[gif|jpg|jpeg|png]$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for ico file validity
	 */
	public function isIcoFile() {
		$result = (boolean) preg_match('/^[a-z0-9_-]+\.ico$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for language code (ISO) validity
	 */
	public function isLanguageIsoCode() {
		$result = (boolean) preg_match('/^[a-z]{2,3}$/ui', $this->_toValidate);
	}

	public function isStateIsoCode() {
		$result = (boolean) preg_match('/^[a-z0-9-]{1,5}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for gender code (ISO) validity
	 */
	public function isGenderIsoCode() {
		$result = (boolean) preg_match('/^[0|1|2|9]$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for gender code (ISO) validity
	 */
	public function isGenderName() {
		$result = (boolean) preg_match('/^[a-z.]+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for a country name validity
	 */
	public function isCountryName() {
		$result = (boolean) preg_match('/^[a-z -]+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for a link (url-rewriting only) validity
	 */
	public function isLinkRewrite() {
		$result = empty($link) OR (boolean) preg_match('/^[_a-z0-9-]+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for a postal address validity
	 */
	public function isAddress() {
		$result = empty($address) OR (boolean) preg_match('/^[^!<>?=+@{}_$%]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for city name validity
	 */
	public function isCityName() {
		$result = (boolean) preg_match('/^[^!<>;?=+@#"�{}_$%]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for search query validity
	 */
	public function isValidSearch()	{
		$result = (boolean) preg_match('/^[^<>;=#{}]{0,64}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for standard name validity
	 */
	public function isGenericName() {
		$result = empty($name) OR (boolean) preg_match('/^[^<>;=#{}]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for HTML field validity (no XSS please !)
	 */
	public function isCleanHtml() {
		$jsEvent = 'onmousedown|onmousemove|onmmouseup|onmouseover|onmouseout|onload|onunload|onfocus|onblur|onchange|onsubmit|ondblclick|onclick|onkeydown|onkeyup|onkeypress|onmouseenter|onmouseleave';
		$result = (!preg_match('/<[ \t\n]*script/ui', $this->_toValidate) && !preg_match('/<.*('.$jsEvent.')[ \t\n]*=/ui', $this->_toValidate)  && !preg_match('/.*script\:/ui', $this->_toValidate));
		return $this->setResult(__FUNCTION__, $result);
	}


	/**
	 * Check for password validity
	 * @param int $size min size of password to validate
	 */
	public function isPasswd($size = 5) {
		$result = (boolean) preg_match('/^[.a-z_0-9-!@#$%\^&*()]{'.$size.',32}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for date validity
	 */
	public function isDate() {
		if (!preg_match('/^([0-9]{4})-((0?[1-9])|(1[0-2]))-((0?[1-9])|([1-2][0-9])|(3[01]))( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/ui', $this->_toValidate, $matches))
		$result = false;
		$result = checkdate(intval($matches[2]), intval($matches[5]), intval($matches[0]));
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for birthDate validity
	 */
	public function isBirthDate() {
		if (empty($this->_toValidate) || $this->_toValidate == '0000-00-00')
		$result = true;
		if ((boolean) preg_match('/^([0-9]{4})-((0?[1-9])|(1[0-2]))-((0?[1-9])|([1-2][0-9])|(3[01]))( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/ui', $this->_toValidate, $birthDate)) {
			if ($birthDate[1] >= date('Y') - 9)
			$result = false;
			$result = true;
		}
		$result = false;
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for boolean validity
	 */
	public function isBool() {
		$result = is_null($this->_toValidate) OR is_bool($this->_toValidate) OR preg_match('/^[0|1]{1}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for phone number validity
	 */
	public function isPhoneNumber() {
		$result = (boolean) preg_match('/^[+0-9. ()-]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for barcode validity (EAN-13)
	 */
	public function isEan13() {
		$result = !$this->_toValidate OR (boolean) preg_match('/^[0-9]{0,13}$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for postal code validity
	 */
	public function isPostCode() {
		$result = (boolean) preg_match('/^[a-z 0-9-]+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for an integer validity
	 */
	public function isInt() {
		$result = ((string)(int)$this->_toValidate === (string)$this->_toValidate OR $this->_toValidate === false);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for an integer validity (unsigned)
	 */
	public function isUnsignedInt()	{
		$result = ($this->isInt($this->_toValidate) AND $this->_toValidate < 4294967296 AND $this->_toValidate >= 0);
		return $this->setResult(__FUNCTION__, $result);
	}


	public function isNullOrUnsignedInt() {
		$result = is_null($id) OR $this->isUnsignedId($this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check object validity
	 */
	public function isLoadedObject() {
		$result = is_object($this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check color validity
	 */
	public function isColor() {
		$result = (boolean) preg_match('/^(#[0-9A-Fa-f]{6}|[[:alnum:]]*)$/ui', $this->_toValidate);
	}

	/**
	 * Check URL validity
	 */
	public function isUrl() {
		$result = (boolean) preg_match('/^([[:alnum:]]|[:#%&_=\(\)\.\? \+\-@\/])+$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check URL validity
	 */
	public function isAbsoluteUrl() {
		if (!empty($url))
		$result = (boolean) preg_match('/^https?:\/\/([[:alnum:]]|[:#%&_=\(\)\.\? \+\-@\/\$])+$/ui', $this->_toValidate);
		$result = true;
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * Check for standard name file validity
	 */
	public function isFileName() {
		$result = preg_match('/^[a-z0-9_.-]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}


	public function isProtocol() {
		$result = preg_match('/^http(s?):\/\/$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}


	public function isSubDomainName() {
		$result = (boolean) preg_match('/^[[:alnum:]]*$/ui', $this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}

	/**
	 * isString method validity
	 */
	public function isString($data){
		$result = is_string($this->_toValidate);
		return $this->setResult(__FUNCTION__, $result);
	}
}



?>
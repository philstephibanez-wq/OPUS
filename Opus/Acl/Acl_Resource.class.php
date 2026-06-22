<?php

#[AllowDynamicProperties]
class ACL_Resource {

    protected $_resourceId;

    public function __construct($resourceId) {
        $this->_resourceId = (string) $resourceId;
    }

    public function getResourceId() {
        return $this->_resourceId;
    }

    public function __toString() {
        return $this->getResourceId();
    }

}

?>

<?php

#[AllowDynamicProperties]
/**
 * Legacy ACL resource descriptor.
 *
 * Represents a protected resource consumed by legacy OPUS ACL checks.
 */
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

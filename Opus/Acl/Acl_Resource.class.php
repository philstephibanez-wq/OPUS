<?php

#[AllowDynamicProperties]
/**
 * OPUS ACL resource descriptor.
 *
 * Represents a protected resource consumed by OPUS ACL checks.
 */
class ACL_Resource  implements ACL_ResourceInterface {

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

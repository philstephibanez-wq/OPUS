<?php

#[AllowDynamicProperties]
class ACL_Role {

    protected $_roleId;

    public function __construct($roleId) {
        $this->_roleId = (string) $roleId;
    }

    public function getRoleId() {
        return $this->_roleId;
    }

    public function __toString() {
        return $this->getRoleId();
    }

}

?>

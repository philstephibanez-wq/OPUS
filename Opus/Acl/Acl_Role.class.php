<?php

#[AllowDynamicProperties]
/**
 * OPUS ACL role descriptor.
 *
 * Represents a role used by OPUS access-control rules.
 */
class ACL_Role  implements ACL_RoleInterface {

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

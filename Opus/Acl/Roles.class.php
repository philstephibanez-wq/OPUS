<?php

#[AllowDynamicProperties]
class ACL_roles {

    protected $_roles = array();

    public function add(ACL_Role $role, $parents = null) {
        $roleId = $role->getRoleId();

        if ($this->has($roleId)) {
            throw new Exception("Role id '$roleId' already exists");
        }

        $roleParents = array();

        if (null !== $parents) {
            if (!is_array($parents)) {
                $parents = array($parents);
            }

            foreach ($parents as $parent) {
                try {
                    if ($parent instanceof ACL_Role) {
                        $roleParentId = $parent->getRoleId();
                    } else {
                        $roleParentId = $parent;
                    }
                    $roleParent = $this->get($roleParentId);
                } catch (ACL_roles_Exception $e) {
                    throw new Exception("Parent Role id '$roleParentId' does not exist", 0, $e);
                }
                $roleParents[$roleParentId] = $roleParent;
                $this->_roles[$roleParentId]['children'][$roleId] = $role;
            }
        }

        $this->_roles[$roleId] = array(
            'instance' => $role,
            'parents' => $roleParents,
            'children' => array()
        );

        return $this;
    }

    public function get($role) {
        if ($role instanceof ACL_Role) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = (string) $role;
        }

        if (!$this->has($role)) {

            throw new Exception("Role '$roleId' not found");
        }

        return $this->_roles[$roleId]['instance'];
    }

    public function has($role) {
        if ($role instanceof Role) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = (string) $role;
        }

        return isset($this->_roles[$roleId]);
    }

    public function getParents($role) {
        $roleId = $this->get($role)->getRoleId();

        return $this->_roles[$roleId]['parents'];
    }

    public function inherits($role, $inherit, $onlyParents = false) {

        try {
            $roleId = $this->get($role)->getRoleId();
            $inheritId = $this->get($inherit)->getRoleId();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $inherits = isset($this->_roles[$roleId]['parents'][$inheritId]);

        if ($inherits || $onlyParents) {
            return $inherits;
        }

        foreach ($this->_roles[$roleId]['parents'] as $parentId => $parent) {
            if ($this->inherits($parentId, $inheritId)) {
                return true;
            }
        }

        return false;
    }

    public function remove($role) {

        try {
            $roleId = $this->get($role)->getRoleId();
        } catch (Exception $e) {
            throw new Exception($e);
        }

        foreach ($this->_roles[$roleId]['children'] as $childId => $child) {
            unset($this->_roles[$childId]['parents'][$roleId]);
        }
        foreach ($this->_roles[$roleId]['parents'] as $parentId => $parent) {
            unset($this->_roles[$parentId]['children'][$roleId]);
        }

        unset($this->_roles[$roleId]);

        return $this;
    }

    public function removeAll() {
        $this->_roles = array();

        return $this;
    }

    public function getRoles() {
        return $this->_roles;
    }

}

?>

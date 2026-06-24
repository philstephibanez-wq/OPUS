<?php

#[AllowDynamicProperties]
/**
 * OPUS ACL coordinator.
 *
 * Provides access-control checks for OPUS resources and roles.
 */
class Acl {

    protected static $_instance = NULL;     // php5.3
    protected $_resources = array();
    protected $_roles;
    protected $_rules = array(
        'allResources' => array(
            'allRoles' => array(
                'allPrivileges' => array(
                    'type' => 'denied',
                    'conditions' => null
                ),
                'byPrivilegeId' => array()
            ),
            'byRoleId' => array()
        ),
        'byResourceId' => array()
    );

    final private function __construct() {
        $this->_roles = new OPUS_ACL_roles();
    }

    final public static function getInstance() {
        if (static::$_instance == null) {
            static::$_instance = new static;
        }
        return static::$_instance;
    }

    /////////////// ROLE

    protected function _getRoles() {
        if (null === $this->_roles) {
            $this->_roles = new OPUS_Acl_roles();
        }
        return $this->_roles;
    }

    public function addRole($role, $parents = null) {
        if (is_string($role)) {
            $role = new OPUS_ACL_Role($role);
        }

        if (!$role instanceof OPUS_ACL_Role) {
            throw new OPUS_Exception('addRole() expects $role to be of type OPUS_ACL_Role');
        }

        $this->_roles->add($role, $parents);
        return $this;
    }

    public function getRole($role) {
        return $this->_roles->get($role);
    }

    public function hasRole($role) {
        return $this->roles->has($role);
    }

    public function inheritsRole($role, $inherit, $onlyParents = false) {
        return $this->roles()->inherits($role, $inherit, $onlyParents);
    }

    public function removeRole($role) {
        $this->Roles->remove($role);

        if ($role instanceof OPUS_Acl_Role) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        foreach ($this->_rules['allResources']['byRoleId'] as $roleIdCurrent => $rules) {
            if ($roleId === $roleIdCurrent) {
                unset($this->_rules['allResources']['byRoleId'][$roleIdCurrent]);
            }
        }
        foreach ($this->_rules['byResourceId'] as $resourceIdCurrent => $visitor) {
            if (array_key_exists('byRoleId', $visitor)) {
                foreach ($visitor['byRoleId'] as $roleIdCurrent => $rules) {
                    if ($roleId === $roleIdCurrent) {
                        unset($this->_rules['byResourceId'][$resourceIdCurrent]['byRoleId'][$roleIdCurrent]);
                    }
                }
            }
        }
        return $this;
    }

    public function removeRoleAll() {
        $this->Roles->removeAll();

        foreach ($this->_rules['allResources']['byRoleId'] as $roleIdCurrent => $rules) {
            unset($this->_rules['allResources']['byRoleId'][$roleIdCurrent]);
        }
        foreach ($this->_rules['byResourceId'] as $resourceIdCurrent => $visitor) {
            foreach ($visitor['byRoleId'] as $roleIdCurrent => $rules) {
                unset($this->_rules['byResourceId'][$resourceIdCurrent]['byRoleId'][$roleIdCurrent]);
            }
        }
        return $this;
    }

    ///////////// RESOURCE
    public function getResources() {
        return array_keys($this->_resources);
    }

    public function addResource($resource, $parent = null) {
        if (is_string($resource)) {
            $resource = new OPUS_Acl_Resource($resource);
        }

        if (!$resource instanceof OPUS_Acl_Resource) {
            throw new OPUS_Exception('addResource() expects $resource to be of type OPUS_Acl_Resource');
        }

        $resourceId = $resource->getResourceId();

        if ($this->has($resourceId)) {
            throw new OPUS_Exception("Resource id '$resourceId' already exists in the ACL");
        }

        $resourceParent = null;

        if (null !== $parent) {
            try {
                if ($parent instanceof OPUS_Acl_Resource) {
                    $resourceParentId = $parent->getResourceId();
                } else {
                    $resourceParentId = $parent;
                }
                $resourceParent = $this->getResource($resourceParentId);
            } catch (Exception $e) {
                throw new OPUS_Exception("Parent Resource id '$resourceParentId' does not exist", 0, $e);
            }
            $this->_resources[$resourceParentId]['children'][$resourceId] = $resource;
        }

        $this->_resources[$resourceId] = array(
            'instance' => $resource,
            'parent' => $resourceParent,
            'children' => array()
        );
        return $this;
    }

    public function getResource($resource) {
        if ($resource instanceof OPUS_ACL_Resource) {
            $resourceId = $resource->getResourceId();
        } else {
            $resourceId = (string) $resource;
        }

        if (!$this->has($resource)) {
            throw new OPUS_Exception("Resource '$resourceId' not found");
        }
        return $this->_resources[$resourceId]['instance'];
    }

    public function has($resource) {
        if ($resource instanceof OPUS_ACL_Resource) {
            $resourceId = $resource->getResourceId();
        } else {
            $resourceId = (string) $resource;
        }
        return isset($this->_resources[$resourceId]);
    }

    public function inherits($resource, $inherit, $onlyParent = false) {
        try {
            $resourceId = $this->getResource($resource)->getResourceId();
            $inheritId = $this->getResource($inherit)->getResourceId();
        } catch (Exception $e) {
            throw new OPUS_Exception($e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $this->_resources[$resourceId]['parent']) {
            $parentId = $this->_resources[$resourceId]['parent']->getResourceId();
            if ($inheritId === $parentId) {
                return true;
            } else if ($onlyParent) {
                return false;
            }
        } else {
            return false;
        }

        while (null !== $this->_resources[$parentId]['parent']) {
            $parentId = $this->_resources[$parentId]['parent']->getResourceId();
            if ($inheritId === $parentId) {
                return true;
            }
        }
        return false;
    }

    public function removeResource($resource) {
        try {
            $resourceId = $this->get($resource)->getResourceId();
        } catch (Exception $e) {
            throw new OPUS_Exception($e->getMessage(), $e->getCode(), $e);
        }

        $resourcesRemoved = array($resourceId);
        if (null !== ($resourceParent = $this->_resources[$resourceId]['parent'])) {
            unset($this->_resources[$resourceParent->getResourceId()]['children'][$resourceId]);
        }
        foreach ($this->_resources[$resourceId]['children'] as $childId => $child) {
            $this->removeResource($childId);
            $resourcesRemoved[] = $childId;
        }

        foreach ($resourcesRemoved as $resourceIdRemoved) {
            foreach ($this->_rules['byResourceId'] as $resourceIdCurrent => $rules) {
                if ($resourceIdRemoved === $resourceIdCurrent) {
                    unset($this->_rules['byResourceId'][$resourceIdCurrent]);
                }
            }
        }

        unset($this->_resources[$resourceId]);
        return $this;
    }

    public function removeAllResources() {
        foreach ($this->_resources as $resourceId => $resource) {
            foreach ($this->_rules['byResourceId'] as $resourceIdCurrent => $rules) {
                if ($resourceId === $resourceIdCurrent) {
                    unset($this->_rules['byResourceId'][$resourceIdCurrent]);
                }
            }
        }

        $this->_resources = array();
        return $this;
    }

    ////////////////RULES

    public function getRoles() {
        return array_keys($this->_getRoles()->getRoles());
    }
    
    public function allow($roles = null, $resources = null, $privileges = null, $conditions = null) {
        return $this->setRule('add', 'allowed', $roles, $resources, $privileges, $conditions);
    }

    public function deny($roles = null, $resources = null, $privileges = null, $conditions = null) {
        return $this->setRule('add', 'denied', $roles, $resources, $privileges, $conditions);
    }

    public function removeAllow($roles = null, $resources = null, $privileges = null) {
        return $this->setRule('remove', 'allowed', $roles, $resources, $privileges);
    }

    public function removeDeny($roles = null, $resources = null, $privileges = null) {
        return $this->setRule('remove', 'denied', $roles, $resources, $privileges);
    }

    public function setRule($operation, $type, $roles = null, $resources = null, $privileges = null, $conditions = null) {

        // ensure that the rule type is valid; normalize input to uppercase
        if ('allowed' !== $type && 'denied' !== $type) {
            throw new OPUS_Exception("Unsupported rule type: '$type'  must be either 'allowed' or 'denied'");
        }

        // ensure that all specified Roles exist; normalize input to array of Role objects or null
        if (!is_array($roles)) {
            $roles = array($roles);
        } else if (0 === count($roles)) {
            $roles = array(null);
        }
        $rolesTemp = $roles;
        $roles = array();
        foreach ($rolesTemp as $role) {
            if (null !== $role) {
                $roles[] = $this->_roles->get($role);
            } else {
                $roles[] = null;
            }
        }
        unset($rolesTemp);

        // ensure that all specified Resources exist; normalize input to array of Resource objects or null
        if ($resources !== null) {
            if (!is_array($resources)) {
                $resources = array($resources);
            } else if (0 === count($resources)) {
                $resources = array(null);
            }
            $resourcesTemp = $resources;
            $resources = array();
            foreach ($resourcesTemp as $resource) {
                if (null !== $resource) {
                    $resources[] = $this->getResource($resource);
                } else {
                    $resources[] = null;
                }
            }
            unset($resourcesTemp, $resource);
        } else {
            $allResources = array(); // this might be used later if resource iteration is required
            foreach ($this->_resources as $rTarget) {
                $allResources[] = $rTarget['instance'];
            }
            unset($rTarget);
        }

        // normalize privileges to array
        if (null === $privileges) {
            $privileges = array();
        } else if (!is_array($privileges)) {
            $privileges = array($privileges);
        }

        switch ($operation) {
            // add to the rules
            case 'add':
                if ($resources !== null) {
                    // this block will iterate the provided resources
                    foreach ($resources as $resource) {
                        foreach ($roles as $role) {
                            $rules = & $this->_getRules($resource, $role, true);
                            if (0 === count($privileges)) {
                                $rules['allPrivileges']['type'] = $type;
                                $rules['allPrivileges']['conditions'] = $conditions;
                                if (!isset($rules['byPrivilegeId'])) {
                                    $rules['byPrivilegeId'] = array();
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    $rules['byPrivilegeId'][$privilege]['type'] = $type;
                                    $rules['byPrivilegeId'][$privilege]['conditions'] = $conditions;
                                }
                            }
                        }
                    }
                } else {
                    // this block will apply to all resources in a global rule
                    foreach ($roles as $role) {
                        $rules = & $this->_getRules(null, $role, true);
                        if (0 === count($privileges)) {
                            $rules['allPrivileges']['type'] = $type;
                            $rules['allPrivileges']['conditions'] = $conditions;
                        } else {
                            foreach ($privileges as $privilege) {
                                $rules['byPrivilegeId'][$privilege]['type'] = $type;
                                $rules['byPrivilegeId'][$privilege]['conditions'] = $conditions;
                            }
                        }
                    }
                }
                break;

            // remove from the rules
            case 'remove':
                if ($resources !== null) {
                    // this block will iterate the provided resources
                    foreach ($resources as $resource) {
                        foreach ($roles as $role) {
                            $rules = & $this->_getRules($resource, $role);
                            if (null === $rules) {
                                continue;
                            }
                            if (0 === count($privileges)) {
                                if (null === $resource && null === $role) {
                                    if ($type === $rules['allPrivileges']['type']) {
                                        $rules = array(
                                            'allPrivileges' => array(
                                                'type' => 'denied',
                                                'conditions' => null
                                            ),
                                            'byPrivilegeId' => array()
                                        );
                                    }
                                    continue;
                                }

                                if (isset($rules['allPrivileges']['type']) &&
                                        $type === $rules['allPrivileges']['type']) {
                                    unset($rules['allPrivileges']);
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    if (isset($rules['byPrivilegeId'][$privilege]) &&
                                            $type === $rules['byPrivilegeId'][$privilege]['type']) {
                                        unset($rules['byPrivilegeId'][$privilege]);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // this block will apply to all resources in a global rule
                    foreach ($roles as $role) {
                        /**
                         * since null (all resources) was passed to this setRule() call, we need
                         * clean up all the rules for the global allResources, as well as the indivually
                         * set resources (per privilege as well)
                         */
                        foreach (array_merge(array(null), $allResources) as $resource) {
                            $rules = & $this->_getRules($resource, $role, true);
                            if (null === $rules) {
                                continue;
                            }
                            if (0 === count($privileges)) {
                                if (null === $role) {
                                    if ($type === $rules['allPrivileges']['type']) {
                                        $rules = array(
                                            'allPrivileges' => array(
                                                'type' => 'denied',
                                                'conditions' => null
                                            ),
                                            'byPrivilegeId' => array()
                                        );
                                    }
                                    continue;
                                }

                                if (isset($rules['allPrivileges']['type']) && $type === $rules['allPrivileges']['type']) {
                                    unset($rules['allPrivileges']);
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    if (isset($rules['byPrivilegeId'][$privilege]) &&
                                            $type === $rules['byPrivilegeId'][$privilege]['type']) {
                                        unset($rules['byPrivilegeId'][$privilege]);
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            default:
                throw new OPUS_Exception("Unsupported operation; must be either 'add' or 'remove'");
        }

        return $this;
    }

    public function isAllowed($role = null, $resource = null, $privilege = null) {
        // reset role & resource to null
        $this->_isAllowedRole = null;
        $this->_isAllowedResource = null;
        $this->_isAllowedPrivilege = null;

        if (null !== $role) {
            // keep track of originally called role
            $this->_isAllowedRole = $role;
            $role = $this->_roles->get($role);
            if (!$this->_isAllowedRole instanceof OPUS_Acl_Role) {
                $this->_isAllowedRole = $role;
            }
        }

        if (null !== $resource) {
            // keep track of originally called resource
            $this->_isAllowedResource = $resource;
            $resource = $this->getResource($resource);
            if (!$this->_isAllowedResource instanceof OPUS_Acl_Resource) {
                $this->_isAllowedResource = $resource;
            }
        }

        if (null === $privilege) {
            // query on all privileges
            do {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->_roleDFSAllPrivileges($role, $resource, $privilege))) {
                    return $result;
                }

                // look for rule on 'allRoles' psuedo-parent
                if (null !== ($rules = $this->_getRules($resource, null))) {
                    foreach ($rules['byPrivilegeId'] as $privilege => $rule) {
                        if ('denied' === ($ruleTypeOnePrivilege = $this->_getRuleType($resource, null, $privilege))) {
                            return false;
                        }
                    }
                    if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, null, null))) {
                        return 'allowed' === $ruleTypeAllPrivileges;
                    }
                }

                // try next Resource
                $resource = $this->_resources[$resource->getResourceId()]['parent'];
            } while (true); // loop terminates at 'allResources' pseudo-parent
        } else {
            $this->_isAllowedPrivilege = $privilege;
            // query on one privilege
            do {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->_roleDFSOnePrivilege($role, $resource, $privilege))) {
                    return $result;
                }

                // look for rule on 'allRoles' pseudo-parent
                if (null !== ($ruleType = $this->_getRuleType($resource, null, $privilege))) {
                    return 'allowed' === $ruleType;
                } else if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, null, null))) {
                    return 'allowed' === $ruleTypeAllPrivileges;
                }

                // try next Resource
                $resource = $this->_resources[$resource->getResourceId()]['parent'];
            } while (true); // loop terminates at 'allResources' pseudo-parent
        }
    }

    protected function _roleDFSAllPrivileges($role, $resource = null) {
        $dfs = array(
            'visited' => array(),
            'stack' => array()
        );

        if (null !== ($result = $this->_roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
            return $result;
        }

        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->_roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    protected function _roleDFSVisitAllPrivileges($role, $resource = null, &$dfs = null) {
        if (null === $dfs) {
            throw new OPUS_Exception('$dfs parameter may not be null');
        }

        if (null !== ($rules = $this->_getRules($resource, $role))) {
            foreach ($rules['byPrivilegeId'] as $privilege => $rule) {
                if ('denied' === ($ruleTypeOnePrivilege = $this->_getRuleType($resource, $role, $privilege))) {
                    return false;
                }
            }
            if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, $role, null))) {
                return 'allowed' === $ruleTypeAllPrivileges;
            }
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->_roles->getParents($role) as $roleParentId => $roleParent) {
            $dfs['stack'][] = $roleParent;
        }

        return null;
    }

    protected function _roleDFSOnePrivilege($role, $resource = null, $privilege = null) {
        if (null === $privilege) {
            throw new OPUS_Exception('$privilege parameter may not be null');
        }

        $dfs = array(
            'visited' => array(),
            'stack' => array()
        );

        if (null !== ($result = $this->_roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
            return $result;
        }

        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->_roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    protected function _roleDFSVisitOnePrivilege($role, $resource = null, $privilege = null, &$dfs = null) {
        if (null === $privilege) {
            throw new OPUS_Exception('$privilege parameter may not be null');
        }

        if (null === $dfs) {
            throw new OPUS_Exception('$dfs parameter may not be null');
        }

        if (null !== ($ruleTypeOnePrivilege = $this->_getRuleType($resource, $role, $privilege))) {
            return 'allowed' === $ruleTypeOnePrivilege;
        } else if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, $role, null))) {
            return 'allowed' === $ruleTypeAllPrivileges;
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->_roles->getParents($role) as $roleParentId => $roleParent) {
            $dfs['stack'][] = $roleParent;
        }

        return null;
    }

    protected function _getRuleType($resource = null, $role = null, $privilege = null) {
        // get the rules for the $resource and $role
        if (null === ($rules = $this->_getRules($resource, $role))) {
            return null;
        }

        // follow $privilege
        if (null === $privilege) {
            if (isset($rules['allPrivileges'])) {
                $rule = $rules['allPrivileges'];
            } else {
                return null;
            }
        } else if (!isset($rules['byPrivilegeId'][$privilege])) {
            return null;
        } else {
            $rule = $rules['byPrivilegeId'][$privilege];
        }

        // check assertion first
        if ($rule['conditions']) {
            $conditions = $rule['conditions'];
            $conditionValue = $conditions->assert(
                    $this, ($this->_isAllowedRole instanceof OPUS_Acl_Role) ? $this->_isAllowedRole : $role, ($this->_isAllowedResource instanceof OPUS_Acl_Resource) ? $this->_isAllowedResource : $resource, $this->_isAllowedPrivilege
            );
        }

        if (null === $rule['conditions'] || $conditionValue) {
            return $rule['type'];
        } else if (null !== $resource || null !== $role || null !== $privilege) {
            return null;
        } else if ('allowed' === $rule['type']) {
            return 'denied';
        } else {
            return 'allowed';
        }
    }

    protected function &_getRules($resource = null, $role = null, $create = false) {
        // create a reference to null
        $null = null;
        $nullRef = & $null;

        // follow $resource
        do {
            if (null === $resource) {
                $visitor = & $this->_rules['allResources'];
                break;
            }
            $resourceId = $resource->getResourceId();
            if (!isset($this->_rules['byResourceId'][$resourceId])) {
                if (!$create) {
                    return $nullRef;
                }
                $this->_rules['byResourceId'][$resourceId] = array();
            }
            $visitor = & $this->_rules['byResourceId'][$resourceId];
        } while (false);

        // follow $role
        if (null === $role) {
            if (!isset($visitor['allRoles'])) {
                if (!$create) {
                    return $nullRef;
                }
                $visitor['allRoles']['byPrivilegeId'] = array();
            }
            return $visitor['allRoles'];
        }
        $roleId = $role->getRoleId();
        if (!isset($visitor['byRoleId'][$roleId])) {
            if (!$create) {
                return $nullRef;
            }
            $visitor['byRoleId'][$roleId]['byPrivilegeId'] = array();
            $visitor['byRoleId'][$roleId]['allPrivileges'] = array('type' => null, 'conditions' => null);
        }
        return $visitor['byRoleId'][$roleId];
    }

    public function __toString() {

        $roles = $this->_roles->getRoles();
        $html = '<ul><h1>Available Roles</h1>';
        foreach ($roles as $role => $params) {
            $html .= '<li>' . $role . '<br />';

            foreach ($params['parents'] as $parent) {
                $html .= '<i>inherits</i>  ' . $parent . '</i>';
            }

            $html .= '</li>';
        }
        $html .= '</ul>';

        $resources = $this->_resources;
        $html .= '<ul><h1>Available Resources</h1>';
        foreach ($resources as $resource => $params) {
            $html .= '<li>' . $resource . '<br />';
            $parent = $params['parent'];
            if (!is_null($parent))
                $html .= '<i>inherits</i>  ' . $parent . '</i>';
            $html .= '</li>';
        }
        $html .= '</ul>';

//        $rules = $this->_rules;
//$html .= "RULES<pre>".print_r($rules, true)."</pre>" ;      
//       $rulesAllResources = $rules['allResources'];
//$html .= "rulesAllResources<pre>".print_r($rulesAllResources, true)."</pre>" ;      
//         $html .= '<ul><h1>Available Rules for All Resources</h1>';
//        foreach ($rulesAllResources as $rule => $ruleType) {
//            $html .= '<li>' . $rule . '<br />';
//            $allRoles = $rule['allRoles'];
//            foreach($allRoles as $rule)
//                $html .= '<i>All Roles $type</i>  ' . $rule['type'] . '<i>';
//                $html .= '</li>';
//            }
//        $html .= '</ul>';
        
        return $html;
    }

}

// OPUS_Acl
?>

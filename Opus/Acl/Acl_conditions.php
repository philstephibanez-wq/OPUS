<?php

#[AllowDynamicProperties]
/**
 * Legacy ACL condition container.
 *
 * Stores additional ACL conditions evaluated by legacy OPUS authorization checks.
 */
abstract class Acl_Conditions {

	 public function assert() {
        $result = true;        
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getMethods() as $method) {
            if (!$method->isPrivate() && strpos($method->getName(), "__") !== 0) {
 				$methodToCall = $method->getName();
//echo "<br />ASSERT: $methodToCall";
				$arguments = array();
				if($methodToCall != 'assert') $result &= call_user_func_array(array($this, $methodToCall), $arguments);
            }
        }
		return $result;
	}
    
} //class
	

	
	





?>
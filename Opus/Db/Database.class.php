<?php

#[AllowDynamicProperties]
class ASAP_BDD_Database {

    function __construct($params=null) {
        if ($params == null)
            return null;

        switch ($params['adapter']) {
            case "flatfile":
                break;
            case "mysql":
            default:
                if (!isset($params['port']))
                    $params['port'] = "3306";
                if (isset($params['prefix'])) {
                    $mysql = new ASAP_BDD_Mysql($params['server'], $params['username'], $params['password'], $params['schema'], $params['port'], $params['prefix']);
                }
                $mysql = new ASAP_BDD_Mysql($params['server'], $params['username'], $params['password'], $params['schema'], $params['port']);
                return $mysql;
        }
        return null;
    }

}

?>
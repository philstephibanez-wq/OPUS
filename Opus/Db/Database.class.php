<?php

#[AllowDynamicProperties]
/**
 * OPUS database base class.
 *
 * Provides the database abstraction surface used by OPUS data-access components.
 */
class OPUS_BDD_Database {

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
                    $mysql = new OPUS_BDD_Mysql($params['server'], $params['username'], $params['password'], $params['schema'], $params['port'], $params['prefix']);
                }
                $mysql = new OPUS_BDD_Mysql($params['server'], $params['username'], $params['password'], $params['schema'], $params['port']);
                return $mysql;
        }
        return null;
    }

}

?>
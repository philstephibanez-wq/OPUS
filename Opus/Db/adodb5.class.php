<?php
if (!class_exists('ADOConnection') && defined('ROOT') && is_file(ROOT . '/framework/libs/adodb5/adodb.inc.php')) { require_once ROOT . '/framework/libs/adodb5/adodb.inc.php'; }

/**
 * Description of adodb5
 *
 * @author Stephane
 */
#[AllowDynamicProperties]
class OPUS_adodb5 extends ADOConnection {

    public static function ADONewConnection($adapter) {
        return self::newConnection($adapter);
    }

    public static function newConnection($adapter) {
        if (!function_exists('ADONewConnection')) {
            throw new OPUS_Exception('ADOdb is not loaded: ADONewConnection() is unavailable.');
        }
        return ADONewConnection($adapter);
    }

}

?>

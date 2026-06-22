<?php

#[AllowDynamicProperties]
class ASAP_Mail {
    public function __construct($params = null, $controller = null) {
        // Historical factory class kept for BC. Use create() for explicit creation.
    }

    public static function create($params = null) {
        if ($params === null) {
            return null;
        }
        switch ($params['adapter'] ?? 'phpmailer') {
            case 'phpmailer':
            default:
                return new ASAP_PhpMailer();
        }
    }
}

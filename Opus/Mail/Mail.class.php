<?php

#[AllowDynamicProperties]
/**
 * OPUS mail facade.
 *
 * Provides the mail-sending surface used by OPUS pages.
 */
class OPUS_Mail  implements OPUS_MailInterface {
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
                return new OPUS_PhpMailer();
        }
    }
}

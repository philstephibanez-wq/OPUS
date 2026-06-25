<?php
if (!class_exists('PHPMailer') && defined('ROOT') && is_file(ROOT . '/framework/libs/PHPMailer/class.phpmailer.php')) { require_once ROOT . '/framework/libs/PHPMailer/class.phpmailer.php'; }

#[AllowDynamicProperties]
/**
 * OPUS PHPMailer adapter for OPUS.
 *
 * Adapts PHPMailer behavior for OPUS mail delivery.
 */
class OPUS_PhpMailer extends PHPMailer {

    public function __construct($exceptions=true) {
        parent::__construct($exceptions); // true for exceptions
        $app = OPUS_Application::getInstance();
        $smtpConf = $app->config->getEnv('smtp') ?: array();
        if (!empty($smtpConf)) {
            if (defined('ROOT')) {
                $this->PluginDir = rtrim(ROOT, '/\\') . '/framework/libs/PHPMailer/';
            }
            $this->isSMTP();
            $this->Host = $smtpConf['host'] ?? 'localhost';
            $this->SMTPAuth = (bool)($smtpConf['auth'] ?? false);
            $this->Port = (int)($smtpConf['port'] ?? 25);
            if (!empty($smtpConf['username'])) $this->Username = $smtpConf['username'];
            if (!empty($smtpConf['password'])) $this->Password = $smtpConf['password'];
            if (!empty($smtpConf['debug'])) $this->SMTPDebug = (int)$smtpConf['debug'];
            if (!empty($smtpConf['timeout'])) $this->Timeout = (int)$smtpConf['timeout'];
        }
    }


}

?>

<?php
if (!class_exists('PHPMailer') && defined('ROOT') && is_file(ROOT . '/framework/libs/PHPMailer/class.phpmailer.php')) { require_once ROOT . '/framework/libs/PHPMailer/class.phpmailer.php'; }
if (!class_exists('OPUS_SmtpConfig') && is_file(__DIR__ . '/SmtpConfig.class.php')) { require_once __DIR__ . '/SmtpConfig.class.php'; }

#[AllowDynamicProperties]
/**
 * OPUS PHPMailer adapter for OPUS.
 *
 * Adapts PHPMailer behavior for OPUS mail delivery.
 * SMTP configuration is validated by the official OPUS_SmtpConfig gate.
 */
class OPUS_PhpMailer extends PHPMailer {

    public function __construct($exceptions=true) {
        parent::__construct($exceptions); // true for exceptions
        if (!class_exists('OPUS_SmtpConfig')) {
            throw new RuntimeException('OPUS_SMTP_CONFIG_CLASS_MISSING');
        }

        $app = OPUS_Application::getInstance();
        $smtpConf = array();
        if (isset($app->config) && is_object($app->config) && method_exists($app->config, 'getEnv')) {
            $candidate = $app->config->getEnv('smtp');
            if (is_array($candidate)) {
                $smtpConf = $candidate;
            }
        }

        $smtpConfig = OPUS_SmtpConfig::fromArray($smtpConf);

        if (defined('ROOT')) {
            $this->PluginDir = rtrim(ROOT, '/\\') . '/framework/libs/PHPMailer/';
        }

        $this->isSMTP();
        $this->Host = $smtpConfig->getHost();
        $this->SMTPAuth = $smtpConfig->isAuthEnabled();
        $this->Port = $smtpConfig->getPort();
        if ($smtpConfig->getUsername() !== '') { $this->Username = $smtpConfig->getUsername(); }
        if ($smtpConfig->getPassword() !== '') { $this->Password = $smtpConfig->getPassword(); }
        if ($smtpConfig->getSecure() !== '') { $this->SMTPSecure = $smtpConfig->getSecure(); }
        $this->SMTPDebug = $smtpConfig->getDebug();
        $this->Timeout = $smtpConfig->getTimeout();
    }


}

?>

<?php
if (!class_exists('PHPMailer') && defined('ROOT') && is_file(ROOT . '/framework/libs/PHPMailer/class.phpmailer.php')) { require_once ROOT . '/framework/libs/PHPMailer/class.phpmailer.php'; }

#[AllowDynamicProperties]
class ASAP_PhpMailer extends PHPMailer {

    public function __construct($exceptions=true) {
        parent::__construct($exceptions); // true for exceptions
        $app = ASAP_Application::getInstance();
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

    public function sample() {
        $this->IsSMTP(); // telling the class to use SMTP
        try {
            $this->Host = "mail.yourdomain.com"; // SMTP server
            $this->SMTPDebug = 2;                     // enables SMTP debug information (for testing)
            $this->AddAddress('whoto@otherdomain.com', 'John Doe');
            $this->SetFrom('name@yourdomain.com', 'First Last');
            $this->AddReplyTo('name@yourdomain.com', 'First Last');
            $this->Subject = 'PHPMailer Test Subject via mail(), advanced';
            $this->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
            $this->MsgHTML(@file_get_contents('contents.html'));
//            $this->AddAttachment('images/phpmailer.gif');      // attachment
//            $this->AddAttachment('images/phpmailer_mini.gif'); // attachment
            $this->Send();
            echo "Message Sent OK</p>\n";
        } catch (phpmailerException $e) {
            throw new ASAP_Exception( $e->errorMessage()); //Pretty error messages from PHPMailer
        } catch (Exception $e) {
            throw new ASAP_Exception( $e->getMessage()); //Boring error messages from anything else!
        }
    }

}

?>

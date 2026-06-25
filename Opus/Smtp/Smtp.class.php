<?php

/*
define('DISPLAY_ERRORS', true);
define('DISPLAY_EXCEPTIONS', true);

set_time_limit(0);
ini_set('memory_limit',-1);
ini_set('max_execution_time',0);
ini_set('ignore_user_abort','On');
ini_set('display_errors', (DISPLAY_ERRORS)?(1):(0));
ini_set('display_startup_errors', (DISPLAY_ERRORS)?(1):(0));
error_reporting((DISPLAY_ERRORS)?(1):(0));
ignore_user_abort (true);
date_default_timezone_set('Europe/Bucharest');

define('SMTP_SERVER','ssl://smtp.gmail.com');
define('SMTP_SERVER_PORT',465);

define('SMTP_USER','address@gmail.com');
define('SMTP_PASSWORD','password');

define('FROM_NAME','SENDER Name');
define('FROM_EMAIL',SMTP_USER);




require_once('SMTP4PHP.php');
use SMTP4PHP\User;

//NOTE: Only if backward compatibility is really needed.
use SMTP4PHP\eMailUser;

use SMTP4PHP\eMail;
use SMTP4PHP\SMTP;

$e = new eMail();
$e->from = new User(FROM_NAME, FROM_EMAIL);
$e->to = new User(FROM_NAME, FROM_EMAIL);
$e->subject = 'SMTP4PHP Test mail';

// EXAMPLE: add inline image example
$e->htmlMessage = 'This is a HTML message!<br><img src="'.$e->addImage('./image.jpg').'" border="0">';

$e->txtMessage = 'This is a TEXT message!';

// EXAMPLE: add attachment example
$e->addAttachment('Attachment.zip');

$smtp = new SMTP(SMTP_SERVER, SMTP_SERVER_PORT, SMTP_USER, SMTP_PASSWORD);
// NOTE: ALL emails are sent through the same connection, speeding up transmission.
try { $smtp->send($e);
 // OR $smtp->send(array($e,$e2));
 }  catch(Exception $e) { }
var_dump($smtp->SMTPlog);

 */






namespace {

    function exception_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
        throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
    }

    set_error_handler("exception_error_handler");
}

namespace SMTP4PHP {
    const VERSION = 2011;
    const RELEASE = 11;

use \Exception,
    \stdClass;

    #[AllowDynamicProperties]
/**
 * SMTP utility toolbox.
 *
 * Provides helper routines used by the embedded SMTP implementation.
 */
abstract class Toolbox {

        protected function _intValue($v) {
            return abs(intval(preg_replace('/[^0-9]/', '', $v)));
        }

        protected function _toSingleDimensionalArray(array $a) {
            $values = new stdClass();
            $values->values = array();
            array_walk_recursive($a, function($value, $key, $obj) { $obj->values[] = $value; }, $values);
            return $values = $values->values;
        }

    }

    #[AllowDynamicProperties]
/**
 * SMTP user value object.
 *
 * Stores SMTP user data used by the embedded mail workflow.
 */
class User {

        protected $name;
        protected $email;

        public function __construct($name = NULL, $email = NULL) {
            if ($email) {
                $this->email = self::validateEmail($email);
            }
            if ($name) {
                $this->name = trim($name);
            }
        }

        public function __get($property) {
            switch ($property = strtoupper(trim($property))) {
                case 'ADDRESS':
                case 'EMAIL':
                    return $this->email;

                case 'NAME':
                    return $this->name;

                default:
                    return NULL;
            }
        }

        public function __set($property, $value) {
            switch ($property = strtoupper(trim($property))) {
                case 'NAME':
                    return $this->name = trim($value);

                case 'ADDRESS':
                case 'EMAIL':
                    return $this->email = self::validateEmail($value);

                default:
                    return NULL;
            }
        }

        public static function __callStatic($name, $arguments) {
            switch (strtoupper(trim($name))) {
                case 'VALIDATEEMAIL':
                    return self::validateEmail((isset($arguments[0])) ? ($arguments[0]) : (NULL));
            }
        }

        public function __toString() {
            return (($this->name) ? ('"' . $this->name . '" ') : ('')) . (($this->email) ? ('<' . $this->email . '>') : (''));
        }

        public function __invoke() {
            return $this->__toString();
        }

        public static function validateEmail($email) {
            if (filter_var($email = strtolower(trim($email)), FILTER_VALIDATE_EMAIL)) {
                return $email;
            } else {
                throw new Exception('Invalid e-mail address: "' . $email . '" !');
            }
        }

    }

    class_alias(__NAMESPACE__ . '\User', __NAMESPACE__ . '\eMailUser');

    #[AllowDynamicProperties]
/**
 * SMTP email message object.
 *
 * Represents an email message handled by the embedded SMTP implementation.
 */
class eMail extends Toolbox {
        const PRIORITY_LOW = 5;
        const PRIORITY_NORMAL = 3;
        const PRIORITY_HIGH = 1;

        const CONTENT_TRANSFER_ENCODING_TEXT = 1;
        const CONTENT_TRANSFER_ENCODING_BASE64 = 64;

        protected $mixedBoundary;
        protected $altBoundary;
        protected $returnPath;
        protected $returnReceipt;
        protected $from;
        protected $replyTo;
        protected $to;
        protected $cc;
        protected $bcc;
        protected $priority;
        protected $charset;
        protected $contentTransferEncoding;
        protected $subject;
        protected $htmlMessage;
        protected $textMessage;
        protected $images;
        protected $attachments;

        protected function _generateRandomString() {
            return md5(uniqid(rand(), true));
        }

        protected function _generateBoundary() {
            return $this->_generateRandomString() . (($this->from) && (preg_match('/(@.+)$/i', $this->from->email, $m) && is_array($m) && ($m = $m[0])) ? ($m) : ($this->_generateRandomString()));
        }

        public function __construct(User $from = NULL, $to = NULL, $subject = NULL, $htmlMessage = NULL, $textMessage = NULL) {
            $this->From = $from;
            $this->To = $to;
            $this->priority = self::PRIORITY_NORMAL;
            $this->charset = 'iso-8859-1';
            $this->contentTransferEncoding = self::CONTENT_TRANSFER_ENCODING_BASE64;
            $this->Subject = $subject;
            $this->HTMLMessage = $htmlMessage;
            $this->TXTMessage = $textMessage;
            $this->images = array();
            $this->attachments = array();
            $this->mixedBoundary = '--=_' . $this->_generateBoundary();
            $this->altBoundary = '--=_' . $this->_generateBoundary();
        }

        public function __get($property) {
            switch (strtoupper(trim($property))) {
                case 'PRIORITY':
                    return $this->priority;

                case 'TRANSFERENCODING':
                case 'CONTENTTRANSFERENCODING':
                    return $this->contentTransferEncoding;

                case 'RETURNPATH':
                    return $this->returnPath;

                case 'RETURNRECEIPT':
                    return $this->returnReceipt;

                case 'FROM':
                    return $this->from;

                case 'REPLYTO':
                    return $this->replyTo;

                case 'TO':
                    return $this->to;

                case 'CC':
                    return $this->cc;

                case 'BCC':
                    return $this->bcc;

                case 'SUBJECT':
                    return $this->subject;

                case 'HTMLMESSAGE':
                    return $this->htmlMessage;

                case 'TEXTMESSAGE':
                case 'TXTMESSAGE':
                    return $this->textMessage;

                case 'RAWMESSAGE':
                    return $this->__toString();

                case 'CHARSET':
                    return $this->charset;

                default:
                    return NULL;
            }
        }

        protected function _set(&$property, &$value) {
            try {
                if ($value) {
                    if ($value instanceof User) {
                        return $property = array($value);
                    } else if (is_array($value)) {
                        for ($values = $this->_toSingleDimensionalArray($value), $i = count($values) - 1; $i > -1; --$i) {
                            if (!($values[$i] instanceof User)) {
                                unset($values[$i]);
                            }
                        }
                        return $property = $values;
                    }
                } else {
                    if (empty($property)) {
                        return $property = array();
                    }
                }
            } catch (Exception $e) {
                return NULL;
            }
        }

        public function __set($property, $value) {
            switch (strtoupper(trim($property))) {
                case 'PRIORITY':
                    return $this->priority = ((($value == self::PRIORITY_HIGH) || ($value == self::PRIORITY_LOW)) ? ($value) : (self::PRIORITY_NORMAL));

                case 'TRANSFERENCODING':
                case 'CONTENTTRANSFERENCODING':
                    return $this->contentTransferEncoding = (($value == self::CONTENT_TRANSFER_ENCODING_BASE64) ? (self::CONTENT_TRANSFER_ENCODING_BASE64) : (self::CONTENT_TRANSFER_ENCODING_TEXT));

                case 'RETURNPATH':
                    return ($value && ($value instanceof User)) ? ($this->returnPath = $value) : (NULL);

                case 'RETURNRECEIPT':
                    return ($value && ($value instanceof User)) ? ($this->returnReceipt = $value) : (NULL);

                case 'FROM':
                    return ($value && ($value instanceof User)) ? ($this->from = $value) : (NULL);

                case 'REPLYTO':
                    return ($value && ($value instanceof User)) ? ($this->replyTo = $value) : (NULL);

                case 'TO':
                    return $this->_set($this->to, $value);

                case 'CC':
                    return $this->_set($this->cc, $value);

                case 'BCC':
                    return $this->_set($this->bcc, $value);

                case 'SUBJECT':
                    return $this->subject = trim(strip_tags($value));

                case 'HTMLMESSAGE':
                    return $this->htmlMessage = trim($value);

                case 'TEXTMESSAGE':
                case 'TXTMESSAGE':
                    return $this->textMessage = preg_replace('/[\f\t ]{2,}/mS', ' ', trim(strip_tags($value)));

                default:
                    return NULL;
            }
        }

        public function __call($name, $arguments) {
            switch (strtoupper(trim($name))) {
                case 'IMG':
                case 'IMAGE':
                case 'ADDIMAGE':
                    return $this->addImage((isset($arguments[0])) ? ($arguments[0]) : (NULL));

                case 'FILE':
                case 'ATTACHMENT':
                case 'ADDATTACHMENT':
                case 'ADDFILE':
                    return $this->addAttachment((isset($arguments[0])) ? ($arguments[0]) : (NULL));

                default:
                    return (string) $this;
            }
        }

        public function addImage($imageURI) {
            try {
                $info = getimagesize($imageURI);
                $cid = $this->_generateRandomString();

                $msg = '--' . $this->mixedBoundary . PHP_EOL;
                $msg .= 'Content-Location: "' . basename($imageURI) . '"' . PHP_EOL;
                $msg .= 'Content-Type: ' . image_type_to_mime_type($info[2]) . PHP_EOL;
                $msg .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
                $msg .= 'Content-ID: <' . $cid . '>' . PHP_EOL;
                $msg .= 'Content-Disposition: inline; filename="' . basename($imageURI) . '"' . PHP_EOL . PHP_EOL;

                $msg .= chunk_split(base64_encode(file_get_contents($imageURI))) . PHP_EOL;
                $this->images[$cid] = $msg;
                return 'cid:' . $cid;
            } catch (Exception $e) {
                return NULL;
            }
        }

        public function addAttachment($attachmentURI) {
            try {
                $cid = $this->_generateRandomString();

                $msg = '--' . $this->mixedBoundary . PHP_EOL;
                $msg .= 'Content-Type: binary/octet-stream' . PHP_EOL;
                $msg .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
                $msg .= 'Content-ID: <' . $cid . '>' . PHP_EOL;
                $msg .= 'Content-Disposition: attachment; filename="' . basename($attachmentURI) . '"' . PHP_EOL . PHP_EOL;

                $msg .= chunk_split(base64_encode(file_get_contents($attachmentURI))) . PHP_EOL;
                $this->attachments[$cid] = $msg;
                return $cid;
            } catch (Exception $e) {
                return NULL;
            }
        }

        public function __toString() {
            try {
                $msg = '';
                if ($this->returnPath) {
                    $msg .= 'Return-Path: <' . $this->returnPath->email . '>' . PHP_EOL;
                }
                if ($this->replyTo) {
                    $msg .= 'Reply-To: ' . $this->replyTo . PHP_EOL;
                }
                $msg .= 'From: ' . $this->from . PHP_EOL;
                if (($this->to) && is_array($this->to) && count($this->to)) {
                    $msg .= 'To: ' . implode(', ', $this->to) . PHP_EOL;
                }
                if (($this->cc) && is_array($this->cc) && count($this->cc)) {
                    $msg .= 'Cc: ' . implode(', ', $this->cc) . PHP_EOL;
                }
                $msg .= 'Subject: ' . $this->subject . PHP_EOL;
                $msg .= 'X-Priority: ' . $this->priority . PHP_EOL;
                $msg .= 'X-MSMail-Priority: ' . (($this->priority == self::PRIORITY_HIGH) ? ('High') : (($this->priority == self::PRIORITY_LOW) ? ('Low') : ('Normal'))) . PHP_EOL;
                $msg .= 'X-Mailer: SMTP4PHP ' . (VERSION) . ', ' . (RELEASE) . 'th release / PHP ' . phpversion() . PHP_EOL;
                if ($this->returnReceipt) {
                    $msg .= ' X-Confirm-Reading-To: ' . $this->returnReceipt . PHP_EOL;
                }
                $msg .= 'MIME-Version: 1.0' . PHP_EOL;
                $msg .= 'Content-Type: multipart/mixed; boundary="' . $this->mixedBoundary . '"' . PHP_EOL;
                $msg .= 'Date: ' . date('r') . PHP_EOL;
                if ($this->returnReceipt) {
                    $msg .= 'Disposition-Notification-To: ' . $this->returnReceipt . PHP_EOL;
                }
                if ($this->returnReceipt) {
                    $msg .= 'Return-Receipt-To: ' . $this->returnReceipt . PHP_EOL;
                }
                $msg .= PHP_EOL;
                $msg .= '--' . $this->mixedBoundary . PHP_EOL;
                $msg .= 'Content-Type: multipart/alternative; boundary="' . $this->altBoundary . '"' . PHP_EOL . PHP_EOL;
                $msg .= '--' . $this->altBoundary . PHP_EOL;
                /* ///////////////////////////////////////// */
                /* text message */
                /* ///////////////////////////////////////// */
                $msg .= 'Content-Type: text/plain; charset="' . $this->charset . '"' . PHP_EOL;
                if ($this->contentTransferEncoding == self::CONTENT_TRANSFER_ENCODING_BASE64) {
                    $msg .= 'Content-Transfer-Encoding: base64' . PHP_EOL . PHP_EOL;
                    $msg .= chunk_split(base64_encode($this->textMessage));
                } else {
                    $this->textMessage = preg_replace('/^\.$/imsSU', '..', $this->textMessage);
                    $msg .= 'Content-Transfer-Encoding: 8bit' . PHP_EOL . PHP_EOL;
                    $msg .= $this->textMessage;
                }
                $msg .= PHP_EOL . PHP_EOL;
                $msg .= '--' . $this->altBoundary . PHP_EOL;
                /* ///////////////////////////////////////// */
                /* html message */
                /* ///////////////////////////////////////// */
                if (empty($this->htmlMessage)) {
                    $this->htmlMessage = nl2br($this->TXTMessage);
                }
                $msg .= 'Content-type: text/html; charset="' . $this->charset . '"' . PHP_EOL;
                if ($this->contentTransferEncoding == self::CONTENT_TRANSFER_ENCODING_BASE64) {
                    $msg .= 'Content-Transfer-Encoding: base64';
                } else {
                    $msg .= 'Content-Transfer-Encoding: 8bit';
                }
                $msg .= PHP_EOL . PHP_EOL;
                if (preg_match('/<\/{0,1}(html|head|body.*)>/imsSU', $this->htmlMessage)) {
                    $htmlMsg = $this->htmlMessage;
                    if ($this->contentTransferEncoding == self::CONTENT_TRANSFER_ENCODING_BASE64) {
                        $htmlMsg = chunk_split(base64_encode($htmlMsg));
                    }
                } else {
                    $htmlMsg = '<html>' . PHP_EOL;
                    $htmlMsg .= '<head>' . PHP_EOL;
                    $htmlMsg .= '<meta http-equiv="content-type" content="text/html; charset="' . $this->charset . '">' . PHP_EOL;
                    $htmlMsg .= '</head>' . PHP_EOL;
                    $htmlMsg .= '<body style="margin-top:0px; margin-bottom:0px; margin-right:0px; margin-left:0px;">' . PHP_EOL;
                    $htmlMsg .= $this->htmlMessage . PHP_EOL;
                    $htmlMsg .= '<br><br></body></html>';
                    if ($this->contentTransferEncoding == self::CONTENT_TRANSFER_ENCODING_BASE64) {
                        $htmlMsg = chunk_split(base64_encode($htmlMsg));
                    }
                }
                $msg .= $htmlMsg;
                unset($htmlMsg);
                $msg .= PHP_EOL . PHP_EOL;
                $msg .= '--' . $this->altBoundary . "--" . PHP_EOL . PHP_EOL;
                /* ///////////////////////////////////////// */
                /* add images */
                /* ///////////////////////////////////////// */
                $msg .= implode('', $this->images);
                /* ///////////////////////////////////////// */
                /* add attachments */
                /* ///////////////////////////////////////// */
                $msg .= implode('', $this->attachments);
                $msg .= '--' . $this->mixedBoundary . '--' . PHP_EOL;
                $msg .= '.' . PHP_EOL;

                return $msg;
            } catch (Exception $e) {
                return NULL;
            }
        }

    }

    #[AllowDynamicProperties]
/**
 * Embedded SMTP client implementation.
 *
 * Provides SMTP protocol operations used by OPUS mail delivery.
 */
class SMTP extends Toolbox {
        const AUTH_AUTO_DETECT = '';
        const AUTH_CRAM_SHA1 = 'CRAM-SHA1';
        const AUTH_CRAM_MD5 = 'CRAM-MD5';
        const AUTH_PLAIN = 'PLAIN';
        const AUTH_LOGIN = 'LOGIN';

        const ENCRYPTION_SSL = 'ssl';
        const ENCRYPTION_TLS = 'tls';

        private $bufferSize = 8192;
        private $ip;
        protected $SMTPlog;
        protected $SMTPserver;
        protected $SMTPport;
        protected $SMTPuser;
        protected $SMTPpassword;
        protected $SMTPauthenticationMethod;
        protected $SMTPconnectionTimeout;
        protected $encryption;
        protected $esmtp;
        protected $smtpConnect;

        public function __construct($SMTPserver = '', $SMTPport = 25, $SMTPuser = '', $SMTPpassword = '', $SMTPauthenticationMethod = self::AUTH_AUTO_DETECT) {
            $this->Server = $SMTPserver;
            $this->Port = $SMTPport;
            $this->User = $SMTPuser;
            $this->Password = $SMTPpassword;
            $this->AuthenticationMethod = $SMTPauthenticationMethod;
            $this->ConnectionTimeout = 30;
            $this->ip = (isset($_SERVER['LOCAL_ADDR'])) ? ($_SERVER['LOCAL_ADDR']) : (gethostbyname(gethostbyaddr('127.0.0.1')));
            $this->esmtp = FALSE;
        }

        public function __destruct() {
            $this->_disconnect();
        }

        public function __sleep() {
            $this->_disconnect();
            return array('SMTPserver', 'SMTPport', 'SMTPuser', 'SMTPpassword', 'SMTPauthenticationMethod', 'SMTPconnectionTimeout', 'encryption', 'esmtp', 'ip');
        }

        public function __clone() {
            $this->_disconnect();
        }

        public function __invoke() {
            $this->send(func_get_args());
        }

        public function __get($property) {
            switch (strtoupper(trim($property))) {
                case 'SERVER':
                case 'SMTPSERVER':
                    return $this->SMTPserver;

                case 'PORT':
                case 'SMTPPORT':
                    return $this->SMTPport;

                case 'TIMEOUT':
                case 'CONNECTIONTIMEOUT':
                case 'SMTPCONNECTIONTIMEOUT':
                    return $this->SMTPconnectionTimeout;

                case 'USER':
                case 'SMTPUSER':
                    return $this->SMTPuser;

                case 'PASSWD':
                case 'PASSWORD':
                case 'SMTPPASSWD':
                case 'SMTPPASSWORD':
                    return $this->SMTPpassword;

                case 'AUTH':
                case 'AUTHENTICATION':
                case 'AUTHENTICATIONMETHOD':
                case 'SMTPAUTH':
                case 'SMTPAUTHENTICATION':
                case 'SMTPAUTHENTICATIONMETHOD':
                    return $this->SMTPauthenticationMethod;

                case 'LOG':
                case 'SMTPLOG':
                    return implode('', $this->SMTPlog);

                case 'ENCRYPTION':
                case 'SMTPENCRYPTION':
                    return $this->encryption;

                default:
                    return NULL;
            }
        }

        public function __set($property, $value) {
            switch (strtoupper(trim($property))) {
                case 'SERVER':
                case 'SMTPSERVER':
                    $this->encryption = (preg_match('/^(' . self::ENCRYPTION_SSL . '|' . self::ENCRYPTION_TLS . '):\/\//i', ($value = trim($value)), $m) && is_array($m) && isset($m[1])) ? (strtolower($m[1])) : ('');
                    return $this->SMTPserver = preg_replace('/^.*:\/\//', '', $value, 1);

                case 'PORT':
                case 'SMTPPORT':
                    return $this->SMTPport = ($value = $this->_intValue($value)) ? ($value) : (25);

                case 'TIMEOUT':
                case 'CONNECTIONTIMEOUT':
                case 'SMTPCONNECTIONTIMEOUT':
                    return $this->SMTPconnectionTimeout = ($value = $this->_intValue($value)) ? ($value) : (30);

                case 'USER':
                case 'SMTPUSER':
                    return $this->SMTPuser = trim($value);

                case 'PASSWD':
                case 'PASSWORD':
                case 'SMTPPASSWD':
                case 'SMTPPASSWORD':
                    return $this->SMTPpassword = $value;

                case 'AUTH':
                case 'AUTHENTICATION':
                case 'AUTHENTICATIONMETHOD':
                case 'SMTPAUTH':
                case 'SMTPAUTHENTICATION':
                case 'SMTPAUTHENTICATIONMETHOD':
                    return $this->SMTPauthenticationMethod = (in_array($value, array(self::AUTH_AUTO_DETECT, self::AUTH_CRAM_SHA1, self::AUTH_CRAM_MD5, self::AUTH_PLAIN, self::AUTH_LOGIN))) ? ($value) : (self::AUTH_AUTO_DETECT);

                default:
                    return NULL;
            }
        }

        public function __call($name, $arguments) {
            switch (strtoupper(trim($name))) {
                case 'SEND':
                case 'SENDMAIL':
                case 'SENDMAILS':
                case 'SENDEMAIL':
                case 'SENDEMAILS':
                case 'SMTPSEND':
                case 'SMTPSENDMAIL':
                case 'SMTPSENDMAILS':
                case 'SMTPSENDEMAIL':
                case 'SMTPSENDEMAILS':
                    return $this->send($arguments);
            }
        }

        protected function _read() {
            $response = '';
            while ($chunk = fread($this->smtpConnect, $this->bufferSize)) {
                $response .= $chunk;
                if (feof($this->smtpConnect) || preg_match('/^\d{3}[^-]/mSU', $chunk)) {
                    break;
                }
            }
            return $this->SMTPlog[] = $response;
        }

        protected function _write($smtpCommand) {
            return fputs($this->smtpConnect, $this->SMTPlog[] = $smtpCommand);
        }

        protected function _exec($smtpCommand, $expectedResponse = NULL) {
            $this->_write($smtpCommand = trim($smtpCommand) . PHP_EOL);
            $smtpResponse = $this->_read();
            if ($expectedResponse && (!preg_match('/^' . $expectedResponse . '/S', $smtpResponse))) {
                throw new Exception('Unexpected SMTP error! (SMTP command: "' . trim($smtpCommand) . '" SMTP response: "' . trim($smtpResponse) . '")');
            }
            return $smtpResponse;
        }

        protected function _disconnect() {
            if (!empty($this->smtpConnect)) {
                try {
                    $this->_exec('QUIT');
                } catch (Exception $e) {

                }
                try {
                    fclose($this->smtpConnect);
                } catch (Exception $e) {

                }
            }
            $this->smtpConnect = NULL;
        }

        protected function _connect() {
            if (empty($this->smtpConnect)) {
                $this->smtpConnect = fsockopen((($this->encryption == self::ENCRYPTION_SSL) ? ($this->encryption . '://') : ('')) . $this->SMTPserver, $this->SMTPport, $errno, $errstr, $this->SMTPconnectionTimeout);
                try {
                    $smtpResponse = trim($this->_read());
                } catch (Exception $e) {

                }
                if (empty($this->smtpConnect)) {
                    throw new Exception('SMTP connection error!' . ($smtpResponse) ? (' (' . $smtpResponse . ')') : (''));
                }
                $xxLO = ($this->esmtp = (stripos($smtpResponse, 'ESMTP') !== FALSE)) ? ('EHLO') : ('HELO');

                stream_set_blocking($this->smtpConnect, true);
                $smtpResponse = trim($this->_exec($xxLO . ' ' . $this->ip, '250'));

                if ($this->encryption == self::ENCRYPTION_TLS) {
                    $this->_exec('STARTTLS', '220');
                    if (!stream_socket_enable_crypto($this->smtpConnect, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new Exception('Unexpected TLS encryption error!');
                    }
                }

                if ($this->SMTPuser) {
                    if (empty($this->SMTPauthenticationMethod)) {
                        if (preg_match('/^250\-?AUTH.*\b(' . self::AUTH_CRAM_SHA1 . ')(?=\b|$)/mSU', $smtpResponse)) {
                            $this->SMTPauthenticationMethod = self::AUTH_CRAM_SHA1;
                        } else
                        if (preg_match('/^250\-?AUTH.*\b(' . self::AUTH_CRAM_MD5 . ')(?=\b|$)/mSU', $smtpResponse)) {
                            $this->SMTPauthenticationMethod = self::AUTH_CRAM_MD5;
                        } else
                        if (preg_match('/^250\-?AUTH.*\b(' . self::AUTH_PLAIN . ')(?=\b|$)/mSU', $smtpResponse)) {
                            $this->SMTPauthenticationMethod = self::AUTH_PLAIN;
                        } else
                        if (preg_match('/^250\-?AUTH.*\b(' . self::AUTH_LOGIN . ')(?=\b|$)/mSU', $smtpResponse)) {
                            $this->SMTPauthenticationMethod = self::AUTH_LOGIN;
                        } else {
                            $this->SMTPauthenticationMethod = self::AUTH_AUTO_DETECT;
                        }
                    }

                    switch ($this->SMTPauthenticationMethod) {
                        case self::AUTH_CRAM_SHA1:
                        case self::AUTH_CRAM_MD5:
                            $this->_exec(base64_encode($this->SMTPuser . ' ' . hash_hmac(ltrim(strtolower($this->SMTPauthenticationMethod), 'cram-'), base64_decode(preg_replace('/^334 /', '', trim($this->_exec('AUTH ' . $this->SMTPauthenticationMethod, '334')))), $this->SMTPpassword)), 235);
                            break;

                        case self::AUTH_PLAIN:
                            $this->_exec('AUTH ' . self::AUTH_PLAIN . ' ' . base64_encode("\0" . $this->SMTPuser . "\0" . $this->SMTPpassword), '235');
                            break;

                        case self::AUTH_LOGIN:
                            $this->_exec('AUTH ' . self::AUTH_LOGIN, '334');
                            $this->_exec(base64_encode($this->SMTPuser), '334');
                            $this->_exec(base64_encode($this->SMTPpassword), '235');
                            break;
                    }
                }
            }
        }

        public function send() {
            try {
                $this->SMTPlog = array();

                for ($emails = $this->_toSingleDimensionalArray(func_get_args()), $i = count($emails) - 1; $i > -1; --$i) {
                    if (!($emails[$i] instanceof eMail)) {
                        unset($emails[$i]);
                    }
                }

                if (count($emails)) {
                    $this->_connect();

                    foreach ($emails as $e) {
                        try {
                            $this->_exec('MAIL FROM: <' . $e->from->email . '>', '250');
                        } catch (Exception $e) {
                            $this->_exec('RSET');
                            throw $e;
                        }

                        if (is_array($e->to)) {
                            foreach ($e->to as $rcpt) {
                                $this->_exec('RCPT TO: <' . $rcpt->email . '>', '250');
                            }
                        }
                        if (is_array($e->cc)) {
                            foreach ($e->cc as $rcpt) {
                                $this->_exec('RCPT TO: <' . $rcpt->email . '>', '250');
                            }
                        }
                        if (is_array($e->bcc)) {
                            foreach ($e->bcc as $rcpt) {
                                $this->_exec('RCPT TO: <' . $rcpt->email . '>', '250');
                            }
                        }

                        try {
                            $this->_exec('DATA', '354');
                        } catch (Exception $e) {
                            $this->_exec('RSET');
                            throw $e;
                        }

                        try {
                            $this->_exec($e->RawMessage, '250');
                        } catch (Exception $e) {
                            $this->_exec('RSET');
                            throw $e;
                        }

                        try {
                            $this->_exec('NOOP', '250');
                        } catch (Exception $e) {

                        }
                    }
                }
            } catch (Exception $e) {
                $this->_disconnect();
                throw $e;
            }
        }

    }

}
?>
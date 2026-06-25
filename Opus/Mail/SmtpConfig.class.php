<?php
/**
 * Typed SMTP configuration guard for OPUS mail delivery.
 *
 * SMTP data must be explicit when mail delivery is requested. This class
 * deliberately provides no localhost/port fallback and exposes only a
 * redacted safe representation for logs, diagnostics and profiler traces.
 */
class OPUS_SmtpConfig
{
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var bool */
    private $auth;
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var string */
    private $secure;
    /** @var int */
    private $debug;
    /** @var int */
    private $timeout;

    private function __construct($host, $port, $auth, $username, $password, $secure, $debug, $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->auth = $auth;
        $this->username = $username;
        $this->password = $password;
        $this->secure = $secure;
        $this->debug = $debug;
        $this->timeout = $timeout;
    }

    /**
     * Build a typed SMTP configuration from OPUS environment data.
     *
     * @param array<string,mixed> $config
     * @return OPUS_SmtpConfig
     * @throws InvalidArgumentException
     */
    public static function fromArray($config)
    {
        if (!is_array($config) || count($config) === 0) {
            throw new InvalidArgumentException('OPUS_SMTP_CONFIG_MISSING');
        }

        $host = self::requiredString($config, 'host', 'OPUS_SMTP_CONFIG_MISSING_HOST');
        $port = self::requiredPort($config, 'port');
        $auth = self::optionalBool($config, 'auth', false);
        $username = '';
        $password = '';

        if ($auth) {
            $username = self::requiredString($config, 'username', 'OPUS_SMTP_CONFIG_MISSING_USERNAME');
            $password = self::requiredString($config, 'password', 'OPUS_SMTP_CONFIG_MISSING_PASSWORD');
        } else {
            if (array_key_exists('username', $config) && trim((string)$config['username']) !== '') {
                $username = trim((string)$config['username']);
            }
            if (array_key_exists('password', $config) && trim((string)$config['password']) !== '') {
                $password = (string)$config['password'];
            }
        }

        $secure = self::optionalSecure($config);
        $debug = self::optionalIntRange($config, 'debug', 0, 0, 4, 'OPUS_SMTP_CONFIG_INVALID_DEBUG');
        $timeout = self::optionalIntRange($config, 'timeout', 30, 1, 300, 'OPUS_SMTP_CONFIG_INVALID_TIMEOUT');

        return new self($host, $port, $auth, $username, $password, $secure, $debug, $timeout);
    }

    /** @return array<string,mixed> */
    public function toSafeArray()
    {
        return array(
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->auth,
            'username' => $this->username !== '' ? '[configured]' : '',
            'password' => $this->password !== '' ? '[redacted]' : '',
            'secure' => $this->secure,
            'debug' => $this->debug,
            'timeout' => $this->timeout,
        );
    }

    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function isAuthEnabled() { return $this->auth; }
    public function getUsername() { return $this->username; }
    public function getPassword() { return $this->password; }
    public function getSecure() { return $this->secure; }
    public function getDebug() { return $this->debug; }
    public function getTimeout() { return $this->timeout; }

    private static function requiredString($config, $key, $errorCode)
    {
        if (!array_key_exists($key, $config)) {
            throw new InvalidArgumentException($errorCode);
        }
        $value = trim((string)$config[$key]);
        if ($value === '') {
            throw new InvalidArgumentException($errorCode);
        }
        return $value;
    }

    private static function requiredPort($config, $key)
    {
        if (!array_key_exists($key, $config)) {
            throw new InvalidArgumentException('OPUS_SMTP_CONFIG_MISSING_PORT');
        }
        return self::intRange($config[$key], 1, 65535, 'OPUS_SMTP_CONFIG_INVALID_PORT');
    }

    private static function optionalBool($config, $key, $default)
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }
        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            if ($value === 0) { return false; }
            if ($value === 1) { return true; }
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, array('1', 'true', 'yes', 'on'), true)) { return true; }
            if (in_array($normalized, array('0', 'false', 'no', 'off', ''), true)) { return false; }
        }
        throw new InvalidArgumentException('OPUS_SMTP_CONFIG_INVALID_BOOL_' . strtoupper($key));
    }

    private static function optionalSecure($config)
    {
        $value = '';
        foreach (array('secure', 'smtp_secure', 'encryption') as $key) {
            if (array_key_exists($key, $config)) {
                $value = strtolower(trim((string)$config[$key]));
                break;
            }
        }
        if ($value === '') {
            return '';
        }
        if (!in_array($value, array('tls', 'ssl'), true)) {
            throw new InvalidArgumentException('OPUS_SMTP_CONFIG_INVALID_SECURE');
        }
        return $value;
    }

    private static function optionalIntRange($config, $key, $default, $min, $max, $errorCode)
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }
        return self::intRange($config[$key], $min, $max, $errorCode);
    }

    private static function intRange($value, $min, $max, $errorCode)
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false || $intValue < $min || $intValue > $max) {
            throw new InvalidArgumentException($errorCode);
        }
        return (int)$intValue;
    }
}
?>

<?php
declare(strict_types=1);

if (!defined('FTP_TIMEOUT')) {
    define('FTP_TIMEOUT', 90);
}
if (!defined('FTP_COMMAND_OK')) {
    define('FTP_COMMAND_OK', 200);
}
if (!defined('FTP_FILE_ACTION_OK')) {
    define('FTP_FILE_ACTION_OK', 250);
}
if (!defined('FTP_FILE_TRANSFER_OK')) {
    define('FTP_FILE_TRANSFER_OK', 226);
}
if (!defined('FTP_COMMAND_NOT_IMPLEMENTED')) {
    define('FTP_COMMAND_NOT_IMPLEMENTED', 502);
}
if (!defined('FTP_FILE_STATUS')) {
    define('FTP_FILE_STATUS', 213);
}
if (!defined('FTP_NAME_SYSTEM_TYPE')) {
    define('FTP_NAME_SYSTEM_TYPE', 215);
}
if (!defined('FTP_PASSIVE_MODE')) {
    define('FTP_PASSIVE_MODE', 227);
}
if (!defined('FTP_PATHNAME')) {
    define('FTP_PATHNAME', 257);
}
if (!defined('FTP_SERVICE_READY')) {
    define('FTP_SERVICE_READY', 220);
}
if (!defined('FTP_USER_LOGGED_IN')) {
    define('FTP_USER_LOGGED_IN', 230);
}
if (!defined('FTP_PASSWORD_NEEDED')) {
    define('FTP_PASSWORD_NEEDED', 331);
}
if (!defined('FTP_USER_NOT_LOGGED_IN')) {
    define('FTP_USER_NOT_LOGGED_IN', 530);
}
if (!defined('FTP_ASCII')) {
    define('FTP_ASCII', 0);
}
if (!defined('FTP_BINARY')) {
    define('FTP_BINARY', 1);
}

/**
 * OPUS FTP helper.
 *
 * This class preserves the historical OPUS_Ftp public API while removing
 * syntax that is not valid on supported PHP 8 runtimes.
 */
#[AllowDynamicProperties]
class OPUS_Ftp implements OPUS_FtpInterface
{
    public $passiveMode = true;
    public $lastLines = [];
    public $lastLine = '';
    public $controlSocket = null;
    public $newResult = false;
    public $lastResult = -1;
    public $pasvAddr = null;

    public $error_no = null;
    public $error_msg = null;

    /**
     * Historical constructor alias retained for callers that still invoke it.
     */
    public function FTP()
    {
    }

    public function connect($host, $port = 21, $timeout = FTP_TIMEOUT)
    {
        $this->_resetError();

        $errNo = 0;
        $errMsg = '';
        $socket = @fsockopen($host, $port, $errNo, $errMsg, $timeout);
        if ($socket === false) {
            $this->_setError(
                $errNo !== 0 ? $errNo : -1,
                $errMsg !== '' ? $errMsg : 'fsockopen failed'
            );
            return false;
        }

        $this->controlSocket = $socket;
        if (@stream_set_timeout($this->controlSocket, (int) $timeout) === false) {
            $this->_setError(-1, 'stream_set_timeout failed');
            return false;
        }

        $this->_waitForResult();
        if ($this->_isError()) {
            return false;
        }

        return $this->getLastResult() === FTP_SERVICE_READY;
    }

    public function isConnected()
    {
        return is_resource($this->controlSocket);
    }

    public function disconnect()
    {
        if (!$this->isConnected()) {
            return;
        }
        @fclose($this->controlSocket);
        $this->controlSocket = null;
    }

    public function close()
    {
        $this->disconnect();
    }

    public function login($user, $pass)
    {
        $this->_resetError();

        $this->_printCommand('USER ' . $user);
        if ($this->_isError()) {
            return false;
        }

        $this->_waitForResult();
        if ($this->_isError()) {
            return false;
        }

        if ($this->getLastResult() === FTP_PASSWORD_NEEDED) {
            $this->_printCommand('PASS ' . $pass);
            if ($this->_isError()) {
                return false;
            }
            $this->_waitForResult();
            if ($this->_isError()) {
                return false;
            }
        }

        return $this->getLastResult() === FTP_USER_LOGGED_IN;
    }

    public function cdup()
    {
        return $this->simpleCommand('CDUP', [FTP_FILE_ACTION_OK, FTP_COMMAND_OK]);
    }

    public function cwd($path)
    {
        return $this->simpleCommand(
            'CWD ' . $path,
            [FTP_FILE_ACTION_OK, FTP_COMMAND_OK]
        );
    }

    public function cd($path)
    {
        return $this->cwd($path);
    }

    public function chdir($path)
    {
        return $this->cwd($path);
    }

    public function chmod($mode, $filename)
    {
        return $this->site('CHMOD ' . $mode . ' ' . $filename);
    }

    public function delete($filename)
    {
        return $this->simpleCommand(
            'DELE ' . $filename,
            [FTP_FILE_ACTION_OK, FTP_COMMAND_OK]
        );
    }

    public function exec($cmd)
    {
        return $this->site('EXEC ' . $cmd);
    }

    public function fget($fp, $remote, $mode = FTP_BINARY, $resumepos = 0)
    {
        $this->_resetError();
        $type = $mode === FTP_ASCII ? 'A' : 'I';

        $this->_printCommand('TYPE ' . $type);
        $this->_waitForResult();
        if ($this->_isError()) {
            return false;
        }

        $result = $this->_download('RETR ' . $remote);
        if ($result !== false) {
            fwrite($fp, $result);
        }

        return $result;
    }

    public function fput($remote, $resource, $mode = FTP_BINARY, $startpos = 0)
    {
        $this->_resetError();
        $type = $mode === FTP_ASCII ? 'A' : 'I';

        $this->_printCommand('TYPE ' . $type);
        $this->_waitForResult();
        if ($this->_isError()) {
            return false;
        }

        if ((int) $startpos > 0) {
            fseek($resource, (int) $startpos);
        }

        return $this->_uploadResource('STOR ' . $remote, $resource);
    }

    public function get_option($option)
    {
        $this->_resetError();

        if ($option === 'FTP_TIMEOUT_SEC') {
            return FTP_TIMEOUT;
        }
        if ($option === 'PHP_FTP_OPT_AUTOSEEK') {
            return false;
        }

        $this->_setError(-1, 'Unknown option: ' . $option);
        return false;
    }

    public function get($local, $remote, $mode = FTP_BINARY, $resumepos = 0)
    {
        $fp = @fopen($local, 'wb');
        if ($fp === false) {
            return false;
        }

        $result = $this->fget($fp, $remote, $mode, $resumepos);
        @fclose($fp);
        if ($result === false) {
            @unlink($local);
        }

        return $result;
    }

    public function mdtm($name)
    {
        $this->_resetError();
        $this->_printCommand('MDTM ' . $name);
        $this->_waitForResult();

        $result = $this->getLastResult();
        if ($this->_isError() || $result !== FTP_FILE_STATUS) {
            return false;
        }

        $subject = trim(substr($this->lastLine, 4));
        if (preg_match(
            '/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/',
            $subject,
            $parts
        ) !== 1) {
            return false;
        }

        return mktime(
            (int) $parts[4],
            (int) $parts[5],
            (int) $parts[6],
            (int) $parts[2],
            (int) $parts[3],
            (int) $parts[1]
        );
    }

    public function mkdir($name)
    {
        return $this->simpleCommand(
            'MKD ' . $name,
            [FTP_PATHNAME, FTP_FILE_ACTION_OK, FTP_COMMAND_OK]
        );
    }

    public function nb_continue()
    {
        $this->_resetError();
        $this->_setError(-1, 'nb_continue not supported');
        return false;
    }

    public function nb_fget()
    {
        $this->_resetError();
        $this->_setError(-1, 'nb_fget not supported');
        return false;
    }

    public function nb_fput()
    {
        $this->_resetError();
        $this->_setError(-1, 'nb_fput not supported');
        return false;
    }

    public function nb_get()
    {
        $this->_resetError();
        $this->_setError(-1, 'nb_get not supported');
        return false;
    }

    public function nb_put()
    {
        $this->_resetError();
        $this->_setError(-1, 'nb_put not supported');
        return false;
    }

    public function nlist($remoteFilespec = '')
    {
        $this->_resetError();
        $result = $this->_download(trim('NLST ' . $remoteFilespec));
        return $result !== false
            ? explode("\n", str_replace("\r", '', trim($result)))
            : false;
    }

    public function pasv($pasv)
    {
        if (!$pasv) {
            $this->_setError(-1, 'Active (PORT) mode is not supported');
            return false;
        }
        $this->passiveMode = true;
        return true;
    }

    public function put($remote, $local, $mode = FTP_BINARY, $startpos = 0)
    {
        $fp = @fopen($local, 'rb');
        if ($fp === false) {
            return false;
        }

        $result = $this->fput($remote, $fp, $mode, $startpos);
        @fclose($fp);
        return $result;
    }

    public function pwd()
    {
        $this->_resetError();
        $this->_printCommand('PWD');
        $this->_waitForResult();

        $result = $this->getLastResult();
        if ($this->_isError() || $result !== FTP_PATHNAME) {
            return false;
        }

        $subject = trim(substr($this->lastLine, 4));
        return preg_match('/"([^"]*)"/', $subject, $parts) === 1
            ? $parts[1]
            : false;
    }

    public function quit()
    {
        $this->close();
    }

    public function raw($cmd)
    {
        $this->_resetError();
        $this->_printCommand($cmd);
        $this->_waitForResult();
        $this->getLastResult();
        return [$this->lastLine];
    }

    public function rawlist($remoteFilespec = '')
    {
        $this->_resetError();
        $result = $this->_download(trim('LIST ' . $remoteFilespec));
        return $result !== false
            ? explode("\n", str_replace("\r", '', trim($result)))
            : false;
    }

    public function ls($remoteFilespec = '')
    {
        $rows = $this->rawlist($remoteFilespec);
        if (!$rows) {
            return $rows;
        }

        $systemType = (string) $this->systype();
        $isWindows = stripos($systemType, 'WIN') !== false;
        $result = [];

        foreach ($rows as $index => $line) {
            if ($isWindows && preg_match(
                '/(\d{2})-(\d{2})-(\d{2}) +(\d{2}):(\d{2})(AM|PM) +(\d+|<DIR>) +(.+)/',
                $line,
                $parts
            ) === 1) {
                $year = (int) $parts[3];
                $year += $year < 70 ? 2000 : 1900;
                $hour = (int) $parts[4];
                if (strcasecmp($parts[6], 'PM') === 0 && $hour < 12) {
                    $hour += 12;
                } elseif (strcasecmp($parts[6], 'AM') === 0 && $hour === 12) {
                    $hour = 0;
                }
                $isDir = $parts[7] === '<DIR>';
                $result[$index] = [
                    'isdir' => $isDir,
                    'size' => $isDir ? 0 : (int) $parts[7],
                    'month' => (int) $parts[1],
                    'day' => (int) $parts[2],
                    'year' => $year,
                    'hour' => $hour,
                    'minute' => (int) $parts[5],
                    'time' => @mktime(
                        $hour,
                        (int) $parts[5],
                        0,
                        (int) $parts[1],
                        (int) $parts[2],
                        $year
                    ),
                    'am/pm' => $parts[6],
                    'name' => $parts[8],
                ];
                continue;
            }

            $parts = preg_split('/\s+/', trim($line), 9);
            if (!is_array($parts) || count($parts) < 8) {
                continue;
            }

            $mode = (string) $parts[0];
            $entry = [
                'isdir' => isset($mode[0]) && $mode[0] === 'd',
                'islink' => isset($mode[0]) && $mode[0] === 'l',
                'perms' => $mode,
                'number' => $parts[1],
                'owner' => $parts[2],
                'group' => $parts[3],
                'size' => $parts[4],
            ];

            if (count($parts) === 8) {
                if (sscanf(
                    $parts[5],
                    '%d-%d-%d',
                    $entry['year'],
                    $entry['month'],
                    $entry['day']
                ) < 3) {
                    continue;
                }
                sscanf(
                    $parts[6],
                    '%d:%d',
                    $entry['hour'],
                    $entry['minute']
                );
                $entry['time'] = @mktime(
                    (int) $entry['hour'],
                    (int) $entry['minute'],
                    0,
                    (int) $entry['month'],
                    (int) $entry['day'],
                    (int) $entry['year']
                );
                $entry['name'] = $parts[7];
            } else {
                $entry['month'] = $parts[5];
                $entry['day'] = $parts[6];
                if (preg_match('/(\d{2}):(\d{2})/', $parts[7], $time) === 1) {
                    $entry['year'] = (int) date('Y');
                    $entry['hour'] = (int) $time[1];
                    $entry['minute'] = (int) $time[2];
                } else {
                    $entry['year'] = (int) $parts[7];
                    $entry['hour'] = 0;
                    $entry['minute'] = 0;
                }
                $entry['time'] = strtotime(sprintf(
                    '%d %s %d %02d:%02d',
                    (int) $entry['day'],
                    (string) $entry['month'],
                    (int) $entry['year'],
                    (int) $entry['hour'],
                    (int) $entry['minute']
                ));
                $entry['name'] = $parts[8];
            }

            $result[$index] = $entry;
        }

        return $result;
    }

    public function rename($from, $to)
    {
        $this->_resetError();

        $this->_printCommand('RNFR ' . $from);
        $this->_waitForResult();
        if ($this->_isError()) {
            return false;
        }

        $this->_printCommand('RNTO ' . $to);
        $this->_waitForResult();
        $result = $this->getLastResult();

        return !$this->_isError()
            && in_array($result, [FTP_FILE_ACTION_OK, FTP_COMMAND_OK], true);
    }

    public function rmdir($name)
    {
        return $this->simpleCommand(
            'RMD ' . $name,
            [FTP_FILE_ACTION_OK, FTP_COMMAND_OK]
        );
    }

    public function set_option()
    {
        $this->_resetError();
        $this->_setError(-1, 'set_option not supported');
        return false;
    }

    public function site($cmd)
    {
        $this->_resetError();
        $this->_printCommand('SITE ' . $cmd);
        $this->_waitForResult();
        $this->getLastResult();
        return !$this->_isError();
    }

    public function size($name)
    {
        $this->_resetError();
        $this->_printCommand('SIZE ' . $name);
        $this->_waitForResult();

        $result = $this->getLastResult();
        if ($this->_isError()) {
            return false;
        }

        return $result === FTP_FILE_STATUS
            ? trim(substr($this->lastLine, 4))
            : false;
    }

    public function ssl_connect()
    {
        $this->_resetError();
        $this->_setError(-1, 'ssl_connect not supported');
        return false;
    }

    public function systype()
    {
        $this->_resetError();
        $this->_printCommand('SYST');
        $this->_waitForResult();

        $result = $this->getLastResult();
        if ($this->_isError()) {
            return false;
        }

        return $result === FTP_NAME_SYSTEM_TYPE
            ? trim(substr($this->lastLine, 4))
            : false;
    }

    public function getLastResult()
    {
        $this->newResult = false;
        return $this->lastResult;
    }

    public function _hasNewResult()
    {
        return $this->newResult;
    }

    public function _waitForResult()
    {
        while (
            !$this->_hasNewResult()
            && $this->_readln() !== false
            && !$this->_isError()
        ) {
        }
    }

    public function _readln()
    {
        if (!$this->isConnected()) {
            $this->_setError(-1, 'FTP control socket is not connected');
            return false;
        }

        $line = fgets($this->controlSocket);
        if ($line === false) {
            $this->_setError(-1, 'fgets failed in _readln');
            return false;
        }
        if ($line === '') {
            return $line;
        }

        if (preg_match('/^(\d{3}) /', $line, $parts) === 1) {
            $this->lastResult = (int) $parts[1];
            $this->newResult = true;
            if (str_starts_with($parts[1], '5')) {
                $this->_setError(
                    $this->lastResult,
                    trim(substr($line, 4))
                );
            }
        }

        $this->lastLine = trim($line);
        $this->lastLines[] = '< ' . trim($line);
        return $line;
    }

    public function _printCommand($line)
    {
        if (!$this->isConnected()) {
            $this->_setError(-1, 'FTP control socket is not connected');
            return false;
        }

        $this->lastLines[] = '> ' . $line;
        if (fwrite($this->controlSocket, $line . "\r\n") === false) {
            $this->_setError(-1, 'fwrite failed in _printCommand');
            return false;
        }
        fflush($this->controlSocket);
        return true;
    }

    public function _pasv()
    {
        $this->_resetError();
        $this->_printCommand('PASV');
        $this->_waitForResult();

        $result = $this->getLastResult();
        if ($this->_isError() || $result !== FTP_PASSIVE_MODE) {
            return false;
        }

        $subject = trim(substr($this->lastLine, 4));
        if (preg_match(
            '/\((\d{1,3}),(\d{1,3}),(\d{1,3}),(\d{1,3}),(\d{1,3}),(\d{1,3})\)/',
            $subject,
            $parts
        ) !== 1) {
            return false;
        }

        $this->pasvAddr = $parts;
        $host = sprintf(
            '%d.%d.%d.%d',
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[3],
            (int) $parts[4]
        );
        $port = ((int) $parts[5] * 256) + (int) $parts[6];

        $errNo = 0;
        $errMsg = '';
        $connection = @fsockopen(
            $host,
            $port,
            $errNo,
            $errMsg,
            FTP_TIMEOUT
        );
        if ($connection === false) {
            $this->_setError(
                $errNo !== 0 ? $errNo : -1,
                $errMsg !== '' ? $errMsg : 'passive connection failed'
            );
            return false;
        }

        return $connection;
    }

    public function _download($cmd)
    {
        $passiveConnection = $this->_pasv();
        if ($passiveConnection === false) {
            return false;
        }

        $this->_printCommand($cmd);
        $this->_waitForResult();
        $this->getLastResult();

        if ($this->_isError()) {
            fclose($passiveConnection);
            return false;
        }

        $result = '';
        while (!feof($passiveConnection)) {
            $chunk = fgets($passiveConnection);
            if ($chunk === false) {
                break;
            }
            $result .= $chunk;
        }
        fclose($passiveConnection);

        $this->_waitForResult();
        $status = $this->getLastResult();
        return in_array(
            $status,
            [FTP_FILE_TRANSFER_OK, FTP_FILE_ACTION_OK, FTP_COMMAND_OK],
            true
        ) ? $result : false;
    }

    public function _uploadResource($cmd, $resource)
    {
        $passiveConnection = $this->_pasv();
        if ($passiveConnection === false) {
            return false;
        }

        $this->_printCommand($cmd);
        $this->_waitForResult();
        $this->getLastResult();

        if ($this->_isError()) {
            fclose($passiveConnection);
            return false;
        }

        while (!feof($resource)) {
            $buffer = fread($resource, 1024);
            if ($buffer === false) {
                fclose($passiveConnection);
                $this->_setError(-1, 'fread failed in _uploadResource');
                return false;
            }
            if ($buffer !== '' && fwrite($passiveConnection, $buffer) === false) {
                fclose($passiveConnection);
                $this->_setError(-1, 'fwrite failed in _uploadResource');
                return false;
            }
        }
        fclose($passiveConnection);

        $this->_waitForResult();
        $status = $this->getLastResult();

        return in_array(
            $status,
            [FTP_FILE_TRANSFER_OK, FTP_FILE_ACTION_OK, FTP_COMMAND_OK],
            true
        );
    }

    public function _resetError()
    {
        $this->error_no = null;
        $this->error_msg = null;
    }

    public function _setError($no, $msg)
    {
        if (is_array($this->error_no)) {
            $this->error_no[] = $no;
            $this->error_msg[] = $msg;
        } elseif ($this->error_no !== null) {
            $this->error_no = [$this->error_no, $no];
            $this->error_msg = [$this->error_msg, $msg];
        } else {
            $this->error_no = $no;
            $this->error_msg = $msg;
        }
    }

    /**
     * Historical public alias retained because legacy code called setError().
     */
    public function setError($no, $msg)
    {
        $this->_setError($no, $msg);
    }

    public function _isError()
    {
        return $this->error_no !== null && $this->error_no !== 0;
    }

    private function simpleCommand(string $command, array $accepted)
    {
        $this->_resetError();
        $this->_printCommand($command);
        $this->_waitForResult();
        $status = $this->getLastResult();

        return !$this->_isError()
            && in_array($status, $accepted, true);
    }
}

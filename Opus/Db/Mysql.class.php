<?php

define('DBCOLOR', 'lightgray');

#[AllowDynamicProperties]
/**
 * OPUS MySQL database adapter.
 *
 * Implements MySQL-specific behavior for the OPUS database layer.
 */
class OPUS_BDD_Mysql {
    protected string $_server = '';
    protected string $_user = '';
    protected string $_pass = '';
    protected string $_database = '';
    protected string $_prefix = '';
    protected int $_port = 3306;
    protected string $_error = '';
    protected int $_errno = 0;
    protected int $_affected_rows = 0;
    /** @var mysqli|null */
    protected $_link_id = null;
    /** @var mysqli_result|bool|null */
    protected $_query_id = null;
    protected string $_script = '';
    protected int $_line = 0;

    public function __construct($server, $user, $password, $database, $port = '3306', $prefix = '') {
        $this->_server = (string)$server;
        $this->_user = (string)$user;
        $this->_pass = (string)$password;
        $this->_database = (string)$database;
        $this->_port = (int)$port;
        $this->_prefix = (string)$prefix;
    }

    private function _parse_tables($query, $array) {
        $out = $query;
        return preg_replace_callback('@\{\{([a-z0-9\-_]*?)\}\}@Si', function ($match) use ($array) {
            $item = $match[1];
            if (!isset($array[$item])) {
                $this->oops('Query Error: Prefix does not match !', __FILE__, __LINE__);
                return $match[0];
            }
            return $this->_prefix . $array[$item];
        }, $out);
    }

    private function trace_query($sql, $script, $line): void {
        if (class_exists('OPUS_Debug')) {
            OPUS_Debug::add($sql, $script, $line, DBCOLOR);
        }
    }

    public function connect($script = '', $line = '', $new_link = false) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->_link_id = mysqli_connect($this->_server, $this->_user, $this->_pass, $this->_database, $this->_port);
        if (!$this->_link_id) {
            $this->oops('Could not connect to server: <b>' . htmlspecialchars($this->_server) . '</b>.', $script, (int)$line);
        }
        if ($this->_link_id) {
            mysqli_set_charset($this->_link_id, 'utf8mb4');
        }
        // Avoid accidental dumps of credentials after connect.
        $this->_server = '';
        $this->_user = '';
        $this->_pass = '';
        $this->_database = '';
        return $this->_link_id;
    }

    public function close($script = __FILE__, $line = __LINE__) {
        if ($this->_link_id instanceof mysqli && !mysqli_close($this->_link_id)) {
            $this->oops('Connection close failed.', $script, $line);
        }
        $this->_link_id = null;
    }

    public function escape($string) {
        if (!$this->_link_id) {
            $this->connect(__FILE__, __LINE__);
        }
        return mysqli_real_escape_string($this->_link_id, (string)$string);
    }

    public function query($sql, $tables = array(), $script = __FILE__, $line = __LINE__) {
        if (!$this->_link_id) {
            $this->connect($script, $line);
        }
        $start = microtime(true);
        $this->_script = (string)$script;
        $this->_line = (int)$line;

        if (!is_array($tables)) {
            $tables = $tables === null ? array() : array('table' => $tables);
        }
        if (count($tables) > 0) {
            $sql = $this->_parse_tables($sql, $tables);
        }

        $this->_query_id = mysqli_query($this->_link_id, $sql);
        if (!$this->_query_id) {
            $this->oops('<b>MySQLi Query fail:</b> ' . htmlspecialchars($sql), $script, $line);
            return false;
        }
        $this->_affected_rows = mysqli_affected_rows($this->_link_id);
        $this->trace_query($sql . ' time: ' . (microtime(true) - $start), $script, $line);
        return $this->_query_id;
    }

    public function lock($table, $how, $script = __FILE__, $line = __LINE__) {
        return $this->query('LOCK TABLES `' . $this->_prefix . $table . '` ' . $how, array(), $script, $line);
    }

    public function unlock($script = __FILE__, $line = __LINE__) {
        return $this->query('UNLOCK TABLES;', array(), $script, $line);
    }

    public function fetch_array($query_id = null) {
        if ($query_id !== null && $query_id !== -1) {
            $this->_query_id = $query_id;
        }
        if ($this->_query_id instanceof mysqli_result) {
            return mysqli_fetch_assoc($this->_query_id);
        }
        $this->oops('Invalid query_id. Records could not be fetched.', $this->_script, $this->_line);
        return false;
    }

    public function fetch_objects($query_id = null) {
        if ($query_id !== null && $query_id !== -1) {
            $this->_query_id = $query_id;
        }
        if ($this->_query_id instanceof mysqli_result) {
            return mysqli_fetch_object($this->_query_id);
        }
        $this->oops('Invalid query_id. Object could not be fetched.', $this->_script, $this->_line);
        return false;
    }

    public function fetch_all_array($query_id = null) {
        $out = array();
        while ($row = $this->fetch_array($query_id)) {
            $out[] = $row;
        }
        $this->free_result($query_id);
        return $out;
    }

    public function free_result($query_id = null): void {
        if ($query_id !== null && $query_id !== -1) {
            $this->_query_id = $query_id;
        }
        if ($this->_query_id instanceof mysqli_result) {
            mysqli_free_result($this->_query_id);
        }
    }

    public function query_first($query_string) {
        $query_id = $this->query($query_string, array(), __FILE__, __LINE__);
        $out = $this->fetch_array($query_id);
        $this->free_result($query_id);
        return $out;
    }

    public function query_update($table, $data, $where = '1', $script = __FILE__, $line = __LINE__, $noquot = false) {
        $q = 'UPDATE `' . $this->_prefix . $table . '` SET ';
        foreach ($data as $key => $val) {
            $valLower = strtolower((string)$val);
            if ($valLower === 'null') {
                $q .= "`$key` = NULL, ";
            } elseif ($valLower === 'now()') {
                $q .= "`$key` = NOW(), ";
            } elseif ($noquot) {
                $q .= "`$key`=" . $val . ', ';
            } else {
                $q .= "`$key`='" . $this->escape($val) . "', ";
            }
        }
        $q = rtrim($q, ', ') . ' WHERE ' . $where . ';';
        return $this->query($q, array(), $script, $line);
    }

    public function query_delete($table, $where, $script = __FILE__, $line = __LINE__) {
        return $this->query('DELETE FROM `' . $this->_prefix . $table . '` WHERE (' . $where . ');', array(), $script, $line);
    }

    public function query_insert($table, $data, $script = __FILE__, $line = __LINE__) {
        $q = 'INSERT INTO `' . $this->_prefix . $table . '` ';
        $v = '';
        $n = '';
        foreach ($data as $key => $val) {
            $n .= "`$key`, ";
            $valLower = strtolower((string)$val);
            if ($valLower === 'null') {
                $v .= 'NULL, ';
            } elseif ($valLower === 'now()') {
                $v .= 'NOW(), ';
            } else {
                $v .= "'" . $this->escape($val) . "', ";
            }
        }
        $q .= '(' . rtrim($n, ', ') . ') VALUES (' . rtrim($v, ', ') . ');';
        if ($this->query($q, array(), $script, $line)) {
            return mysqli_insert_id($this->_link_id);
        }
        return false;
    }

    public function oops($msg = '', $script = __FILE__, $line = __LINE__) {
        if ($this->_link_id instanceof mysqli) {
            $this->_error = mysqli_error($this->_link_id);
            $this->_errno = mysqli_errno($this->_link_id);
        } else {
            $this->_error = mysqli_connect_error() ?: '';
            $this->_errno = mysqli_connect_errno() ?: 0;
        }
        $errorMsg = '<table align="center" border="1" cellspacing="0" style="background:white;color:black;width:80%;">';
        $errorMsg .= '<tr><th colspan="2">Database Error</th></tr>';
        $errorMsg .= '<tr><td align="right" valign="top">Message:</td><td>' . $msg . '</td></tr>';
        $errorMsg .= '<tr><td align="right" valign="top" nowrap>MySQLi Error:</td><td>' . htmlspecialchars($this->_error) . '</td></tr>';
        $errorMsg .= '<tr><td align="right">Date:</td><td>' . date('d/m/y \t h:i:s ') . '</td></tr>';
        $errorMsg .= '<tr><td align="right">Uri:</td><td>' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . '</td></tr>';
        $errorMsg .= '<tr><td align="right">Referer:</td><td>' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '') . '</td></tr>';
        $errorMsg .= '<tr><td align="right">File:</td><td>Script: ' . htmlspecialchars(basename((string)$script)) . ', line: ' . (int)$line . '</td></tr>';
        $errorMsg .= '</table>';
        if (class_exists('OPUS_Debug')) {
            OPUS_Debug::add($errorMsg, __CLASS__ . '::' . __FUNCTION__, __LINE__, 'red');
        }
        throw new OPUS_Exception(strip_tags($msg . ' ' . $this->_error), $this->_errno);
    }
}

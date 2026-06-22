<?php

/**
 * OPUS REST base controller.
 *
 * Minimal PHP 8 compatible REST layer for the legacy OPUS MVC runtime.
 * It keeps the historical router/controller contract and adds explicit JSON
 * responses, HTTP method dispatch, request body parsing and lightweight CORS.
 */
#[AllowDynamicProperties]
abstract class OPUS_REST_Rest extends OPUS_Controller {
    protected array $_restAllowedMethods = array('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');
    protected string $_restMethod = 'GET';
    protected array $_restInput = array();
    protected string $_restRawInput = '';
    protected bool $_restInputParsed = false;

    public function run() {
        OPUS_Debug::addDump(__CLASS__ . '::' . __FUNCTION__ . ' parameters', $this->_params, __FILE__, __LINE__, 'cyan');

        $this->_restMethod = $this->method();
        if (!in_array($this->_restMethod, $this->_restAllowedMethods, true)) {
            return $this->error(405, 'method_not_allowed', 'HTTP method not allowed.', array(
                'method' => $this->_restMethod,
                'allowed' => $this->_restAllowedMethods,
            ));
        }

        $this->response = false;
        $this->init();
        $this->before_action();

        if ($this->_restMethod === 'OPTIONS') {
            return $this->noContent(204);
        }

        $baseAction = isset($this->_params['action']) ? (string)$this->_params['action'] : 'default';
        $method = strtolower($this->_restMethod);
        $candidates = array(
            $baseAction . '_' . $method . '_action',
            $method . '_action',
            $baseAction . '_action',
            'default_action',
        );

        foreach ($candidates as $action) {
            if (method_exists($this, $action)) {
                $this->{$action}();
                $this->after_action();
                return $this->response;
            }
        }

        return $this->error(404, 'rest_action_not_found', 'REST action not found.', array(
            'controller' => static::class,
            'action' => $baseAction,
            'method' => $this->_restMethod,
        ));
    }

    public function default_action() {
        return $this->error(404, 'endpoint_not_found', 'Endpoint not found.');
    }

    protected function method(): string {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $override = strtoupper((string)$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            if (in_array($override, array('PUT', 'PATCH', 'DELETE'), true)) {
                return $override;
            }
        }
        return $method;
    }

    protected function enableCors($origins = '*', $methods = null, $headers = null): void {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $allowedOrigin = '*';

        if (is_array($origins)) {
            if ($origin !== '' && in_array($origin, $origins, true)) {
                $allowedOrigin = $origin;
            } elseif (in_array('*', $origins, true)) {
                $allowedOrigin = '*';
            } else {
                $allowedOrigin = '';
            }
        } elseif (is_string($origins) && $origins !== '') {
            $allowedOrigin = $origins;
        }

        if ($allowedOrigin !== '' && !headers_sent()) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Vary: Origin', false);
            header('Access-Control-Allow-Methods: ' . ($methods ?: implode(', ', $this->_restAllowedMethods)));
            header('Access-Control-Allow-Headers: ' . ($headers ?: 'Content-Type, Accept, X-Requested-With, X-HTTP-Method-Override'));
        }
    }

    protected function query(?string $key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
    }

    protected function input(?string $key = null, $default = null) {
        if (!$this->_restInputParsed) {
            $this->_parseInput();
        }
        if ($key === null) {
            return $this->_restInput;
        }
        return array_key_exists($key, $this->_restInput) ? $this->_restInput[$key] : $default;
    }

    protected function rawInput(): string {
        if (!$this->_restInputParsed) {
            $this->_parseInput();
        }
        return $this->_restRawInput;
    }

    protected function param(string $key, $default = null) {
        return array_key_exists($key, $this->_params) ? $this->_params[$key] : $default;
    }

    protected function requestData(): array {
        return array(
            'params' => $this->publicParams(),
            'query' => $_GET,
            'input' => $this->input(),
            'method' => $this->_restMethod,
        );
    }

    protected function publicParams(): array {
        $params = $this->_params;
        unset($params['module_path'], $params['controller_path']);
        return $params;
    }

    protected function success($data = array(), int $status = 200, array $meta = array()) {
        return $this->json(array(
            'ok' => true,
            'data' => $data,
            'meta' => $meta,
        ), $status);
    }

    protected function error(int $status, string $code, string $message, array $details = array()) {
        return $this->json(array(
            'ok' => false,
            'error' => array(
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ),
        ), $status);
    }

    protected function noContent(int $status = 204) {
        $this->_clearOutputBuffers();
        http_response_code($status);
        exit;
    }

    protected function json($payload, int $status = 200, array $headers = array()) {
        $this->_clearOutputBuffers();
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            foreach ($headers as $name => $value) {
                header((string)$name . ': ' . (string)$value);
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    private function _parseInput(): void {
        $this->_restInputParsed = true;
        $this->_restRawInput = (string)file_get_contents('php://input');
        $this->_restInput = array();

        if ($this->_restRawInput === '') {
            if (!empty($_POST)) {
                $this->_restInput = $_POST;
            }
            return;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false || $this->_looksLikeJson($this->_restRawInput)) {
            $decoded = json_decode($this->_restRawInput, true);
            if (is_array($decoded)) {
                $this->_restInput = $decoded;
            }
            return;
        }

        $parsed = array();
        parse_str($this->_restRawInput, $parsed);
        if (is_array($parsed)) {
            $this->_restInput = $parsed;
        }
    }

    private function _looksLikeJson(string $value): bool {
        $value = ltrim($value);
        return $value !== '' && ($value[0] === '{' || $value[0] === '[');
    }

    private function _clearOutputBuffers(): void {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}

?>

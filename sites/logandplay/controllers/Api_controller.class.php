<?php

#[AllowDynamicProperties]
class Api_controller extends ASAP_Controller {
    public function default_action() { return $this->site_action(); }

    public function site_action() {
        $app = ASAP_Application::getInstance();
        $site = $app->getSite();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'ok' => true,
            'site' => $site ? $site->toArray() : null,
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'package_logs' => $app->getSiteLogDir(),
            'package_tmp' => $app->getSiteTmpDir(),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

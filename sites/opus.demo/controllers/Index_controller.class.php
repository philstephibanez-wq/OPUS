<?php

#[AllowDynamicProperties]
class Index_controller extends ASAP_Controller {
    public function default_action() { return $this->show_action(); }

    protected function _loadPackageClasses(): void {
        $base = rtrim((string)$this->getParam('module_path'), '/\\');
        require_once $base . '/helpers/PackageContent_helper.class.php';
        require_once $base . '/views/Site_view.class.php';
    }

    public function show_action() {
        $this->_loadPackageClasses();
        PackageContent_helper::prepare($this);
        $slug = isset($this->pg) ? (string)$this->pg : '';
        PackageContent_helper::renderPage($this, $slug);
        return 'end';
    }

    public function doc_action() {
        $this->_loadPackageClasses();
        PackageContent_helper::prepare($this);
        $doc = isset($this->doc) ? (string)$this->doc : '';
        PackageContent_helper::renderDoc($this, $doc);
        return 'end';
    }
}

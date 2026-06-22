<?php

#[AllowDynamicProperties]
class Site_view extends ASAP_VIEW_Html {
    protected function init() {
        $tpl = $this->_controller->getTemplateEngine('x64');
        $tpl->loadTemplate('site.tpl');
        $this->setEncoding('utf-8');
        $tpl->assignAll();
        $this->add($tpl->parse());
    }
}

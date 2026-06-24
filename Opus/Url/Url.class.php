<?php

#[AllowDynamicProperties]
/**
 * OPUS URL helper.
 *
 * Builds and resolves URLs for OPUS routing and navigation code.
 */
class OPUS_Url_Url {
    public $_protocol;
    public $_host;
    public $_path;
    public $_arguments;
    public $_anchor;
    
    public function __construct($url='', $path='', $arguments=array(), $anchor='') {
         $app = OPUS_Application::getInstance();
         
         if($url != '') {
             $this->parse($url);
         } else {
            $this->_protocol = $app->getProtocol();
            $this->_host = $app->getDomain(); //www.zone-webmasters.net
            $this->_path = $path; // page/controller without left /
            $this->_arguments = $arguments;
            $this->_anchor = $anchor;             
         }
    }
    
    protected function parse($url) {
        $params = parse_url($url);
        $this->_protocol = $params['scheme']; //http
        $this->_host = $params['host']; //www.zone-webmasters.net
        $this->_path = $params['path']; //page.php
        $this->_arguments = $params['query'];
        $this->_anchor = $params['fragment'];
    }
         
    public function __toString() {
        $url = $this->_protocol . ":" . $this->_host. $this->_path;
        if($this->_arguments != '') $url .= http_build_query($this->_arguments);
        if($this->_anchor != '') $url .= '#' . $this->_anchor;
        return $url;
    }
    
    public function setProtocol($protocol) { $this->_protoco = $protocol; }
    public function getProtocol($protocol) { return $this->_protoco; }
     
    public function setHost($host) { $this->_host = $host; }
    public function getHost($host) { return $this->_host; }
    
    public function setPath($path) { $this->_path = $path; }
    public function getPath($path) { return $this->_path; }
    
    public function setArguments($arguments) { $this->_arguments = $arguments; }
    public function getArguments($arguments) { $this->_arguments; }
    
    public function setAnchor($anchor) { return $this->_anchor = $anchor; }
    public function getAnchor($anchor) { return $this->_anchor; }
}

?>

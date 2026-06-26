<?php
#[AllowDynamicProperties]
/**
 * OPUS link component kept for compatibility.
 *
 * Represents a link component used by OPUS rendering surfaces while the framework migrates to namespaced components.
 */
class OPUS_LINK_Link implements OPUS_LINK_LinkInterface {
    public $_HTMLLink = '';
    private $_url;
    private $_hasAnchor = false;
    private $_label;
    private $_class;
    private $_id;
    private $_block;
    private $_mode;


    public function __construct($url, $label, $class='', $id='', $parameters = array(), $mode = 'html', $block = 'layout') {
        $this->_url = $url;
        if(count($parameters) > 0) {
            foreach($parameters as $order => $value) {
                $this->_url .= "/" . $value;
            }
        }
        $this->_label = $label;
        $this->_block = $block;
        $this->_mode  = $mode;
        $this->_class = $class;
        $this->_id  = $id;

        if($this->_class != '') $class = 'class="'.$this->_class.'"';
        if($this->_id != '') $id = 'id="'.$this->_id.'"';
        $this->_HTMLLink = "<a $class $id " . $this->_generateLink($url) . " >" . $label . "</a>";
    }

    private function _generateLink() {
        $link = '';
        switch ($this->_mode) {
            case 'ajax':
                switch ($this->_block) {
                    case 'popup':
                        $link = ' href="javascript:;" onclick="ajaxPopup(\'' . $this->_url . '\');" ';
                        break;
                    case 'content':
                    default:
                        $link = ' href="javascript:;" onclick="ajaxCall(\'' . $this->_url . '\');" ';
                        break;
                }
                break;
            case 'html':
            default:
                $link = ' href="' . $this->_url . '" ';
        }
        return $link;
    }

    public function __toString() {
        return $this->_HTMLLink;
    }

    public function getMode() {
        return $this->_mode;
    }

     public function getBlock() {
        return $this->_block;
    }

    public function changeClass($class) {
        $this->_class = $class;
        $id = '';
        if($this->_class != '') $class = 'class="'.$this->_class.'"';
        if($this->_id != '') $id = 'id="'.$this->_id.'"';
        $this->_HTMLLink = "<a $class $id " . $this->_generateLink() . " >" . $this->_label . "</a>";
   }

    public function changeId($id) {
        $this->_id = $id;
        $class = '';
        if($this->_class != '') $class = 'class="'.$this->_class.'"';
        if($this->_id != '') $id = 'id="'.$this->_id.'"';
        $this->_HTMLLink = "<a $class $id " . $this->_generateLink() . " >" . $this->_label . "</a>";

    }

} // class link





?>

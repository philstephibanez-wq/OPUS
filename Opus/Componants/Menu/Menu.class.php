<?php

#[AllowDynamicProperties]
class OPUS_MENU_Menu {
    protected $_class ;
    protected $_app = null;
    protected $_i18n = null;
    protected $_controller = null;
    protected $_nodes;

    function __construct($menuClasss='topnav', $addDefaults=true) {
        $this->_class = $menuClasss;
        $this->_app = OPUS_Application::getInstance();
        $this->_controller = OPUS_Controller::getInstance();
        $this->_i18n = OPUS_I18N_I18n::getInstance();
        if($addDefaults) $this->_defaults = $this->_getDefaultLinks();
    }
    
    protected function _insertNode($menuClasss, $link, $parentClass=null, $pos=null) {
        $tmpAr = array();
        if($parentClass == null) {
        // parent node
            for($b=0; $b < $pos; $b++) {
               $tmpAr[$menuClasss][$b] =  $this->_nodes[$menuClasss][$b];
            }
            $tmpAr[$menuClasss][$pos] =  $link;
            for($e=$pos; $e < count($this->_nodes[$menuClasss]); $e++) {
               $tmpAr[$menuClasss][$e+1] =  $this->_nodes[$menuClasss][$e];
            }           
        } else {
        // child node
            for($b=0; $b < $pos; $b++) {
               $tmpAr[$menuClasss][$parentClass][$b] =  $this->_nodes[$menuClasss][$parentClass][$b];
            }
            $tmpAr[$menuClasss][$parentClass][$pos] =  $link;
            for($e=$pos; $e < count($this->_nodes[$menuClasss][$parentClass]); $e++) {
               $tmpAr[$menuClasss][$parentClass][$e+1] =  $this->_nodes[$menuClasss][$parentClass][$e];
            }                       
        }
        return $tmpAr;
    }
    
    // nodes seulement 2 niveaux.
    public function addNode($menuClasss, $link, $parentClass=null, $pos=null) {
        if($parentClass == null) {
        // parent node
            if($pos == null) {
            // on ajoute en fin   
               $this->_nodes[$menuClasss][] = $link;               
            } else {
            // on insertÃƒÆ’Ã‚Â  la position
                if(isset($this->_nodes[$menuClasss][$pos])) {
                   $this->_nodes = $this->_insertNode($menuClasss, null, $pos, $link);                     
                } else {
                    $this->_nodes[$menuClasss][$pos] = $link;
                }
            }
        } else {
        /// child node
// echo "<br><h1><font color='orange'>CHILD NODE $parentClass $menuClasss </font></h1>"; 
            if($pos == null) {
            // on ajoute en fin   
                 $this->_nodes[$parentClass][] = $link;               
            } else {
            // on insertÃƒÆ’Ã‚Â  la position
                if(isset($this->_nodes[$parentClass][$pos])) {
                   $this->_nodes = $this->_insertNode($menuClasss, $parentClass, $pos, $link); 
                } else {
                    $this->_nodes[$parentClass][$pos] = $link;
                }
            }            
        }
    }

    public function removeNode($menuClasss, $parentClass, $chldId) {
        
    }

    

    private function _getDefaultLinks() {
        // icic  on récupère le niveau 0 du menu avec les valeurs par éfaut
        $routes = $this->_app->getRoutesMenu($this->_class);
        $urls = array();

//echo "<br> <font color='white'>MENU ".$this->_class." <pre> ".print_r($routes, true)."</pre></font>";

        foreach ($routes as $num => $route) {
            $modules = explode('|', $route->target['module']);
            foreach($modules as $m => $module) {           
            $path = $route->rule;
//echo "<hr><font color='red'> MODULE $module</font>";                   
               $module = $this->_i18n->translate($module, 1, 'N', true); 
//echo "<br><font color='green'> MODULE translated $module</font>"; 
              // rechercher les paramètres par défaut ex: :name= all
               foreach($route->target as $key => $value) {
                   if($key == 'module') continue;
                   $values = explode('|', $value);
                   $value = $values[0]; // only default target  
//echo "<br><font color='orange'>NUM: $num, KEY: $key => $value</font>";                   
                   $value = $this->_i18n->translate($value, 1, 'N', true);
//echo "<br><font color='orange'>NUM: $num, KEY: $key => $value</font>";
                   $path = str_replace(":$key", $value, $path); 
                   if($key == 'controller') $controller = $value;
                   $path = str_replace(":module", $module, $path);
               }
               $path = ltrim($path, '/');
               $id = $module."_".$controller;             
               $label = ucfirst($module);
               list($mode, $block) = explode(':', $route->method);
               $url = new OPUS_URL_Url('', $path); 
//echo "<br>$name <b>PATH</b> ===== <font color='orange'>$path, $mode, $block</font>";
           
               $link = new OPUS_LINK_Link($url, $label, "title" , "", array(), $mode, $block);            
               $links[$id] = $link;                
//echo "<br><b>LINK $id</b> ===== <font color='white'>$link</font>";
               $this->addNode($id, $link, null, null);
            }           
        }        
//echo "<br><b>LINKS $id</b> ===== <font color='red'><pre>".print_r($links, true)."</pre></font>";
        
        return $links;
    }

    public function addItems($items=false) {
        if(!$items) return;
        $keys = array_keys($items);
// echo "<br><font color='yellow'><pre>".print_r($keys, true)."</pre></font>"; 
        $nodeId = $keys[0];
        $thisNode = $this->_nodes[$nodeId];
        $mode = $thisNode[0]->getMode();
        $block = $thisNode[0]->getBlock();
        
//echo "<br><font color='green'>NODE<pre>".print_r($thisNode, true)."</pre></font>"; 
        
        foreach($items[$nodeId] as $num => $params) {
// echo "<br><font color='yellow'>$num<pre>".print_r($params, true)."</pre></font>"; 
                list($module, $controller) = explode("_", $nodeId);
                $path = $module."/".$controller."/".$params['urlParms'];
               $url = new OPUS_URL_Url('', $path); 
//echo "<br>$num <b>PATH</b> ===== <font color='orange'>$path, $mode, $block</font>";
               $label = $params['label'];
               $link = new OPUS_LINK_Link($url, $label, "", "", array(), $mode, $block);    
            $this->addNode($nodeId."_".$num, $link, $nodeId);
        }
//echo "<br><font color='yellow'>nodes<pre>".print_r($this->_nodes, true)."</pre></font>"; 
 
    }
    
     public function getCurrent() { }
    
    
     public function generateHtmlDDMenu() {
//echo "<font color='white'><pre>".print_r($items,true)."</pre></font>";

         echo "<br>CURRENT (à implémenter " . $this->getCurrent();
         
//        echo "<br> <font color='white'>NODES<pre>".print_r($this->_nodes, true)."</pre></font>";
 
//        $this->addItems($items);
      
        $menuHTML = '';
        foreach($this->_nodes as $name => $links) {
// echo "<br><font color='white'>$name<pre>".print_r($links,true)."</pre></font>";
            $hasChildren = false;
            $level0 = array_shift($links);
            $level1HTML = '';
            $level1 = $links;   
            
            if(count($level1) > 0) $hasChildren = true;
            if($hasChildren) {
                $level0->changeClass("arrow");
            }
            $level0HTML = "    <li  id=\"title\" >".$level0->__toString()."\n\r";            
 
            if($hasChildren) {
               foreach($links as $num => $link) {
                   $level1HTML .= "        <li id=\"item\" >".$link->__toString()."</li>\n\r";
               }
            }
            if($level1HTML != '') $level1HTML = "<ul id=\"items_block\" >\n\r".$level1HTML."</ul>\n\r";
            $menuHTML .= $level0HTML.$level1HTML."</li>\n\r";
        } // each nodes
        return    "<div class=\"".$this->_class."_wrapper\" >\n\r  <ul class=\"".$this->_class."\">".$menuHTML."  </ul>\n\r</div>";

    }
     public function generateHtmlMenu() {
//echo "<font color='white'><pre>".print_r($items,true)."</pre></font>";

         echo "<br>CURRENT (à implémenter " . $this->getCurrent();
         
//        echo "<br> <font color='white'>NODES<pre>".print_r($this->_nodes, true)."</pre></font>";
 
//        $this->addItems($items);
      
        $menuHTML = '';
        foreach($this->_nodes as $name => $links) {
// echo "<br><font color='white'>$name<pre>".print_r($links,true)."</pre></font>";
            $hasChildren = false;
            $level0 = array_shift($links);
            $level1HTML = '';
            $level1 = $links;   
            
            if(count($level1) > 0) $hasChildren = true;
            if($hasChildren) {
                $level0->changeClass("arrow");
            }
            $level0HTML = "    <li  id=\"title\" >".$level0->__toString()."</li>\n\r";            
 
            if($hasChildren) {
               foreach($links as $num => $link) {
                   $level1HTML .= "        <li id=\"item\" >".$link->__toString()."\n\r";
               }
            }
            if($level1HTML != '') $level1HTML = "<ul id=\"items_block\" >\n\r".$level1HTML."</ul>\n\r";
            $menuHTML .= $level0HTML.$level1HTML."</li>\n\r";
        } // each nodes
        return    "<div class=\"".$this->_class."_wrapper\" >\n\r  <ul class=\"".$this->_class."\">".$menuHTML."  </ul>\n\r</div>";
        

        }
    
}

?>

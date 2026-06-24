<?php
	
#[AllowDynamicProperties]
/**
 * Legacy OPUS debug helper.
 *
 * Provides debugging utilities used by older OPUS runtime code.
 */
abstract class OPUS_Debug {
		static $_logs = array();
		static $_root = '../';
		static $_debug = false;
		
		static public function setDebug($debug=true, $root='../../logs') {
			self::$_debug = $debug;
			self::$_root = $root;
		}
				
 		static public function get() {
			if(self::$_debug) { 
                if (count(self::$_logs) === 0) return "";
				$totaltime = 0;
				$sub_time = 0;
				$logs = "<ul class='debug' id='debug'>";
				$start_time = self::$_logs[0]->time;
				$last_time = $start_time;
				$last_script =  self::$_logs[0]->script;
				for($l = 0; $l < count(self::$_logs); $l++){
					if($last_script != self::$_logs[$l]->script) {
						$last_script = self::$_logs[$l]->script;
						$logs .= "<li>SUB TIME: ".number_format($sub_time, 4)."</li>";
						$sub_time = 0;
					}
					$logs .= "<li class='debug'><span class='debug' id='debug'>".($l+1).": ";
					$dt = number_format(self::$_logs[$l]->time - $last_time, 4);
					$last_time = self::$_logs[$l]->time;
					$logs .= " Script: ".self::$_logs[$l]->script." Line: ".self::$_logs[$l]->line."</span>";
					$logs .= " Time: ".$dt;
					$logs .= " Memory: ".intval(memory_get_usage()/1000)."K";
					$logs .= "<br/><span style='color:".self::$_logs[$l]->color.";'>".self::$_logs[$l]->msg."</span>";
					$logs .= "</li>";
					$totaltime += $dt;
					$sub_time += $dt;
				}
				$logs .= "<li>TOTAL TIME: ".number_format($totaltime, 4)." Memory: ".intval(memory_get_usage()/1000)."K</li>";
				$logs .= "</ul>";
				
				self::$_logs = array();
				$time = date("Y_m_d G.i.s", time());
				return $logs;
			} else return "";
		}
		
		static public function add($msg, $script, $line, $color='black', $logIt=false) {
			if(self::$_debug) {
				$newItem = new stdClass();
				$newItem->msg = "<pre>".$msg."</pre>";
				$newItem->script = basename($script);
				$newItem->line = $line;
				$newItem->color = $color;
				$newItem->time = microtime(true);
				self::$_logs[] = $newItem;
				if($logIt) error_log(date("\nd.m.Y h:i:s")." Line: $line | $msg", 3, self::$_root."/".basename($script).".log"); 
			}
		}		

		static public function addDump($objName, $obj, $script, $line, $color='black', $logIt=false) {
			if(self::$_debug) {
				$newItem = new stdClass();
				$objStr = print_r($obj, true);
//				$objStr = str_replace(array('<', '>'), array('&lt;', '&gt;'), $objStr);
				$newItem->msg = "<h3>$objName</h3> Count: ".(is_countable($obj) ? count($obj) : 1)."<pre>".$objStr."</pre>";
				$newItem->script = basename($script);
				$newItem->line = $line;
				$newItem->color = $color;
				$newItem->time = microtime(true);
				self::$_logs[] = $newItem;
				if($logIt) error_log(date("\nd.m.Y h:i:s")." Line: $line | OBJECT: $objName \n$objStr", 3, self::$_root."/".basename($script).".log"); 
			}
		}
		
        static public function addClasses($script, $line, $color='black', $logIt=false) {
        	if(self::$_debug) {
				$classes = get_declared_classes();
				self::addDump("DECLARED CLASSES", $classes, $script, $line, $color, $logIt);
        	}
		}

	} // class





?>
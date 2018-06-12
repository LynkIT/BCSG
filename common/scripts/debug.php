<?php
function printd($debug, $ref='?', $height='100', $tag='pre') {
	if(is_int($ref)) { $height=$ref; $ref=''; }

	//Detect SQL query
	if(is_string($debug)) {
		if(preg_match('/[SELECT|COUNT|INSERT|DELETE|CURDATE]/',$debug)) {
			$debug = htmlentities($debug);
			$height*=2;
			if(file_exists(DIR_FS_SCLASSES.'class.highlighter.php')) {
				require_once(DIR_FS_SCLASSES.'class.highlighter.php');
				$hl = new Highlighter();
				$debug = $hl->sql($debug);
			}
		}
	}

	echo '<div class="debug_tab" style="background:#FFF;color:#000;height:'.$height.'px;padding:5px;border:1px solid #aaa;overflow:auto"><'.$tag.'><h2>'.$ref.'</h2>';
	if(is_array($debug)) print_r($debug);
	elseif(is_object($debug)) var_dump($debug);
	else echo $debug;
	echo '</'.$tag.'></div>';
}

function fatal_handler() {
	$error = error_get_last();
	if($error!==NULL) {
		$err = print_r($error,true);
		echo '<pre style="background:#3CB5BC;border:1px solid #AAA;color:#FFF;height:auto;font-weight:bold;font-size:10px;line-height:12px;width:1400px;position:absolute;bottom:20px;left:20px;z-index:1000;padding:10px;overflow:hidden;">'.$err.'</pre>';
	}
}

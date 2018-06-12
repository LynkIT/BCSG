<?php
/*
	The purpose of this file is to provide the system with static general purpose methods
	In order to maintain cohesion, integrity and quality, this is the only place where 
	static methods should be declared, other that the Registry getter.
*/

class Functions {
	public static function getModuleCodeFromURI() {
		//Find the module code from the URI
		$_default = 'home'; //default entry point
		$_REMOTEADDR = isset($_SERVER['REMOTE_ADDR'])? $_SERVER['REMOTE_ADDR'] : '';
		$_REQUEST_URI = isset($_SERVER['REQUEST_URI'])? $_SERVER['REQUEST_URI'] : '';
		$_uri = basename($_REQUEST_URI);

		$scriptname = preg_replace("/^\//","",$_SERVER['SCRIPT_NAME']);
		if($_uri=='index.php' || $_uri==$scriptname) return $_default;
		if(preg_match("/^\?/", $_uri)) $_uri = 'index.php'.$_uri; //Convert short codes to full script name
		if(strpos($_uri, "?")) {
			//Code appears to be correctly formatted, determine the module code...
			$_pcode = substr($_uri, strpos($_uri,"?")+1);
			if(strpos($_pcode, "&")) $_pcode = substr($_pcode, 0, strpos($_pcode, "&"));
			if(!strlen($_pcode)||strpos($_pcode,"=")||strpos($_pcode,".")||(strlen($_pcode)<2)) $_pcode = substr($_uri, 0, strpos($_uri, "."));
		} else {
			//No idea what type of code this is, return the default
			$_pcode = $_default;
		}

		return $_pcode;
	}

	public static function dater($in) {
		//Custom method to convert date style MMM-YYYY into SQL friendly YYYY-MM-DD
		if(preg_match("/^\d{4}-\d{2}-\d{2}$/",$in)) return $in; //Already in correct format

		$months = array('jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12);
		$month = date("m");
		$year = date("Y");

		if(preg_match("/^[a-z]{3}/",$in)) {
			$mon = substr(strtolower($in),0,3);
			$month = isset($months[$mon])? $months[$mon] : $mon;
		}

		if(preg_match("/\d{4}$/",$in)) $year = substr($in,-4);
		return "$year-$month-01";
	}
}
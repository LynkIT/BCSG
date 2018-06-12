<?php
/*
	Simple wrapper for more user friendly session handling.
	Includes a reset method to return the session to default values.
*/

class Session {
	public $name; //Inner ID - NOT actual Session ID
	public $vars;
	public $ttl = 36000; //10 hours=36000 - 30 days=2592000
	public $saved = false;
	protected $reset;

	public function __construct($name, $defaults=array()) {
		$this->name = defined("COOKIE_NAME")? str_replace(COOKIE_NAME, "", COOKIE_NAME.$name) : $name;
		if(!defined("COOKIE_DOOD")) define("COOKIE_DOOD", false);

		//Load config from session or create new default.
		if(isset($_SESSION[$this->name])) $json = $_SESSION[$this->name];
		elseif(isset($_COOKIE[$this->name])) $json = $_COOKIE[$this->name];
		else $json = "";

		$this->reset = $defaults;
		if(strlen($json)) {
			$this->saved = true;
			$this->vars = json_decode($json,true);
		} else {
			$this->vars = $defaults;
		}
	}

	public function set($key, $val) 		{ $this->vars[$key] = $val; }
	public function get($key, $def=NULL)	{ return isset($this->vars[$key])? $this->vars[$key] : $def; }
	public function save($docookie=false) {
		//Serialise the values using JSON and save to session and cookie...
		$json = json_encode($this->vars);
		$_SESSION[$this->name] = $json;
		if(COOKIE_DOOD||$docookie) setcookie($this->name, $json, ($this->ttl + time()));
		$this->saved=true;
	}

	public function reset() {
		//reset to defaults...
		$this->vars = $this->reset;
		$this->save();
		setcookie($this->name, "", 1);
		$this->saved=false;
	}
}

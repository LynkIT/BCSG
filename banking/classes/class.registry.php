<?php
/*
	Registry:: This is the central connector for all other classes
	within the system. Instead of instantiating new objects, register
	them here and reuse the existing objects anywhere in the system with the accessor:

	Registry::getInstance()->get(XXX);

	For example Database connections can be pooled rather than creating 
	multiple DB objects. The User object can be made system wide and accessed
	through the registry rather than using static User methods.
*/

class Registry {
	protected $assignments 	= array();
	public $cmts 			= array();
	public $err 			= array();
	public $systems 		= array();

	public function __construct() {
		//Lets setup some useful variables
		$this->register("year", date("Y"));
		$this->register("IP", (isset($_SERVER['REMOTE_ADDR'])? $_SERVER['REMOTE_ADDR'] : ''));
		$this->register("POST", $_POST);
		$this->register("GET", $_GET);
	}

	public static function &getInstance() {
		static $me;
		if(is_object($me)==true) return $me;
		$me = new Registry; return $me;
	}

	public function close() {
		session_write_close();
		ob_end_flush();
	}

	//Use this section for the assignments/var registry
	public function set($name, $object) 		{ $this->register($name, $object); }
	public function get($name, $default=NULL) 	{ return $this->val($name, $default); }
	public function assign($name, $val) 		{ return $this->register($name, $val); }
	public function register($name, $val) 	{
		if(is_array($name)) {
			foreach($name as $key=>$value) {
				if(is_array($value)) {
					$this->assignments[$key]=$value;
					foreach($value as $a=>$b) $this->assignments[$key.".".$a] = $b;
				} else {
					$this->assignments[$key] = $value;
				}
			}
		} else {
			if(is_array($val)) {
				$this->assignments[$name]=$val;
				foreach($val as $a=>$b) $this->assignments[$name.".".$a] = $b;
			} else $this->assignments[$name] = $val;
		}
	}

	public function val($name, $default=NULL) {
		if(isset($this->assignments[$name])) return $this->assignments[$name];
		return $default;
	}
}
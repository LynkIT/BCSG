<?php
/*
	This class, together with the compiler class allow the developer to use a robust MVC
	model and strict separation between PHP code and HTML. Instantiate a Theme or call
	on an existing theme via the registry to output dynamic content HTML (or TPL)

	You can assign variables to the theme at PHP level to be used within the HTML content.

	examples:
	$theme->assign("MYVAR",array('A','B','C'));
	$theme->display("mytestarray.tpl");

	Note that some functionality will not work without supporting classes such as User and DB
*/

class Theme {
	private $site;
	protected $compiled;
	public $cache = false;
	protected $cachefile;
	protected $tpl_name = '';
	protected $tpl_contents = '';
	protected $log_access = false; //Logs folder must be writable to enable logging.

	public function __construct($pcodeoverride = '') {
		$this->site = Registry::getInstance();
	}

	public function compileTPL($filename='', $optimise=false) {
		if(empty($filename)) $filename = DIR_FS_TPL . "index.tpl";
		if(!strcmp($this->tpl_name, $filename)==0) {
			$this->tpl_name = $filename;
			if(strpos($filename, ".tpl")) {
				//create file pointer
				if(!file_exists($filename)) die("$filename is missing...");
				if(!$fp=fopen($filename,"r")) return $this->onFail("Could not open $filename",$fp);
				if(!$this->tpl_contents = file_get_contents($filename)) return $this->onFail("<b>[WARNING]:</b> Cannot read contents of $filename",$fp);
				fclose($fp);

				if($user = $this->site->get("usr")) if(is_object($user)) $user = $user->name();
				$timestamp = date('Y-m-d H:i:s');
				if(isset($_SERVER['HTTP_HOST']) && defined("DIR_FS_LOGS")) {
					$logFile = DIR_FS_LOGS.'access.log';
					$logString = "[$timestamp]";
					$logString .= (strpos($_SERVER['REQUEST_URI'],"act="))? " [ajax]" : " [main]";
					$logString .= " {$_SERVER['REMOTE_ADDR']} {$_SERVER['REQUEST_URI']} $user";
					if(file_exists($logFile) && filesize($logFile) > 100000000 OR intval(date("H")) > 21) unlink($logFile);
					if($this->log_access) file_put_contents($logFile, "$logString\n", FILE_APPEND | LOCK_EX);
				}
			}
		}

		if(!class_exists("Compiler", false)) die("Compiler is not loaded!");
		$mc = new Compiler($this->tpl_contents, $this->tpl_name);
		return $mc->content($optimise, true);
	}

	public function display($filename='', $optimise=false) {
		echo $this->compileTPL($filename, $optimise);
	}

	public function assign($name, $value = NULL) {
		//Use site registry to hold assignments
		$this->site->assign($name, $value); 
		return 1;
	}

	public function prepend($name, $value) {
		$add = $value . $this->site->val($name);
		$this->site->assign($name, $add);
	}

	public function append($name, $value) {
		$add = $this->site->val($name) . $value;
		$this->site->assign($name, $add);
	}

	private function onFail($msg,$fp) {
		//Close any open file pointers and log / display the error message
		fclose($fp);
		die($msg);
	}
}

class Compiler {
	protected $site;
	protected $_content = '';
	protected $tplfilename = '';

	//Relating to IF blocks
	protected $block_open = 0;					//Level depth of IF block
	protected $block_eval = array(1);			//Evals IF to true or false
	protected $block_evald = array(0);			//Denotes whether eval is already completed (history of elseif's}

	//Relating to FOR blocks
	protected $buffer_id = 0;
	protected $buffer = array('');
	protected $fblock_for = '';
	protected $fblock_key = array('NOTHING');
	protected $fblock_value = array('NOTHING');
	protected $fblock_array = array(array());
	protected $compiler = true;					//Switch the compiler on or off

	public function __construct($content, $tplfilename='') {
		$this->tplfilename = $tplfilename;
		if(!$content) return;
		$this->site = Registry::getInstance();
		$this->_content = $content;
		$this->compile();
	}

	//Public interface
	public function content($optimise = false, $unescape=false, $clean=false) {
		return $this->_content;
//		return Normaliser::content($this->_content);
	}

	//Compiler
	protected function compile() {
		//compiler errors IF { is first char in content so just in case - add a space at start
		$content = " " . $this->_content;
		$compiled = '';

		//COMPILE THE BLOCKS ////////////////////////////////////////////////////////////////////
		if(strpos($content, "{")) { //No sense running a slow pregger if no blocks exist
			//preg_split the content - use greedy, case sensitivity not required but included for... a laugh
			$pieces = preg_split("/\{(.*)\}/iU", $content, -1, PREG_SPLIT_DELIM_CAPTURE);

			//Code blocks are always at an odd numbered array key
			if(count($pieces)) {
				for($i = 0; $i<count($pieces); $i++) {
					if($i%2) { //Odd number - is a command, so process it
						$pieces[$i] 	= $this->process($pieces[$i]);			//Process the command
						$pieces[$i+1] 	= $this->buffer($pieces[$i+1]);	//Get the following content piece
					}
				}
				$compiled = implode("", $pieces);
			} else $compiled = $content;
		} else $compiled = $content;

		$content = $compiled; $compiled = '';
		//END BLOCK COMPILE //////////////////////////////////////////////////////////////////////////
	
		//This next section is so that single line braces can be included in the HTML
		//without buggering up the compilation process. Using [[ and ]] bypasses the {} extraction.
		$content = str_replace("[@", "{", $content);
		$content = str_replace("@]", "}", $content);
		$this->_content = trim($content);
	}

	private function buffer($piece) {
		//The purpose of this method is to determine whether to display the subsequent content ($piece)
		if(!$this->compiler) return $piece;
		if($this->block_eval[$this->block_open]) {
			$this->buffer[$this->buffer_id] .= $piece;
		}
		$returnpiece = $this->buffer[0];
		$this->buffer[0] = '';
		return $returnpiece;
	}

	private function combine_buffers() {
		//Take the topmost buffer and insert it into buffer[X-1]
		//Reset the topmost to empty, and deduct buffer_id
		//buffer_id should NEVER be zero at the start of this method
		if($this->buffer_id < 1) return $this->err("ERROR: Too many endfor's");

		//Do a foreach on the current fblock and place it into buffer[X-1]
		$fo = $this->buffer_id; $tmp = $tmp2 = '';
		if(is_array($this->fblock_array[$fo])) {
			$_counter = 0;
			$_countarr = count($this->fblock_array[$fo]);

			foreach($this->fblock_array[$fo] as $key=>$val) {
				$_counter++;

				//We need a miniCompiler here to compile the internal block in the buffer!
				//Place the keys and values into the registry...
				$tmp = $this->buffer[$fo]; //Always preserve buffer until outside the foreach block
				$this->site->register(substr($this->fblock_key[$fo],1), $key);
				$this->site->register(substr($this->fblock_value[$fo],1), $val);

				if(is_array($val)) {
					//Multi-dimesional array
					$val['__COUNT']		= count($this->fblock_array[$fo]);
					$val['__LAST'] 		= ($_counter==$_countarr)? 1 : 0;
					$val['__FIRST'] 	= ($_counter==1)? 1 : 0;
					$val['__ODD'] 		= ($_counter%2>0)? 1 : 0;
					$val['__POSITION']	= $_counter;

					foreach($val as $innerkey => $innerval) {
						$tmpkey = substr($this->fblock_value[$fo],1).'.'.$innerkey;
						$this->site->register($tmpkey, $innerval);
					}

				
				}

				$mc = new Compiler($tmp, true, true);
				$tmp2 .= $mc->content();
				unset($mc);
			}
		} else {
			$this->err("Not an array in buffer $fo ({$this->fblock_key[$fo]}:{$this->fblock_value[$fo]})",false);
		}

		array_pop($this->buffer);
		$this->buffer_id--;
		$this->buffer[$this->buffer_id] .= $tmp2;

	}

	private function process($piece) {
		//First check the toggler
		$originalpiece = $piece;

		//Ignore remarks...
		if(substr($piece,0,1)=="*") return '';
		if(substr($piece,0,1)=="#") return '';
		if(strtolower(substr($piece,0,4))=="rem ") return '';

		if($piece == "compiler=on") { $this->compiler = true; return ''; }
		if(!$this->compiler) return $this->buffer('{' . $piece . '}');

		$this->bt = false;	

		//Create a name value pair array - changed or reset by following.
		$nvp = array(trim($piece));

		if($this->buffer_id > 0) {
			if(strcmp(substr($nvp[0],0,7),"foreach")==0) {
				//Passthrough
			} else {
				if((strcmp($nvp[0],"/foreach")!=0) && (strcmp($nvp[0],"endfor")))
					return $this->buffer('{' . $originalpiece . '}'); //Replace the command, process it later
			}
		}

		if(substr($piece,0,1) == "$") {
			//This is an internal variable
			$var = substr($piece,1);
			$piece = $this->site->val($var);
			return $this->buffer($this->site->val($var));
		
		} else {
			//remove spaces from = type command
			while(strpos($piece, " =")) $piece = str_replace(" =", "=", $piece);
			while(strpos($piece, "= ")) $piece = str_replace("= ", "=", $piece);
			$tmp = explode("=", $piece);

			//Deal with spaced commands (eg: foreach $tmp as $t)
			if($pos = strpos($tmp[0], " ")) {				//Space before =
				$nvp[0] = substr($piece,0,$pos);
				$nvp[1] = trim(substr($piece,$pos+1));
			} elseif($pos = strpos($piece, "=")) {			//= before space
				$nvp[0] = substr($piece,0,$pos);
				$nvp[1] = trim(substr($piece,$pos+1));
			} 

			$nvp[0] = strtolower(trim($nvp[0]));
			$nvp[1] = isset($nvp[1])? strval($nvp[1]) : "";
		}

		//Clear out the piece ready for a return value
		$piece = '';
		if(isset($nvp[1]) && (strlen($nvp[1]) > 0)) { //Parameter is present and non-empty
			//Now look for multiple parameters
			$params = explode(",",$nvp[1]);

			switch($nvp[0]) {
				case "func": case "function":
					$piece = $this->_func($nvp[1]);
					break;
				case 'block' : 
					//Insert a new Block
					if(!class_exists("Block", false)) require_once(DIR_FS_CLASSES."class.content.php");
					$block = new Block($params[0]);
					if($block->type() == $this->typename) $piece = $block->content();
					break;
				case "bring":
					$piece = $this->includeFile($nvp[1], false);

					//If this is css or js, wrap it in tags, no compile
					if(preg_match("/\.css$/", $nvp[1])) $piece = $this->minify($piece,'css');
					if(preg_match("/\.js$/", $nvp[1])) $piece = $this->minify($piece,'js');
					break;
				case "include": case "includefile":
					$piece = $this->includeFile($nvp[1]);
					break;
				case "foreach": case "each":
					$fo = ++$this->buffer_id;
					$this->buffer[$fo] = ''; //Open an empty string

					//Split up the parts
					if(strpos($nvp[1], " as ") > 0) {
						$tmp = explode(" as ", $nvp[1]);
						$tmp2 = explode("=>", $tmp[1]);
						$parts = array(trim($tmp[0]), trim($tmp2[0]));
						if(isset($tmp2[1])) $parts[] = trim($tmp2[1]);
					}

					$this->fblock_key[$fo] = isset($parts[2])? $parts[1] : 'NOTHING';		//eg: $key
					$this->fblock_value[$fo] = isset($parts[2])? $parts[2] : (isset($parts[1])? $parts[1] : 'NOTHING'); //eg: $val
					$this->fblock_array[$fo] = $this->_getPiece($parts[0]); //eg: $children (array)
					break;
				case 'if':
					$this->block_open ++;
					$this->block_evald[$this->block_open] = 0;
					$this->doIf($nvp[1]);
					break;
				case "elseif": case "/elseif":
					//Check for already evaluated to true - thus MUST be false
					if($this->block_evald[$this->block_open]) $this->block_eval[$this->block_open] = 0;
					else $this->doIf($nvp[1]);
					$piece = ''; break;
				case "assign":
					$this->assign($nvp[1]);
					break;
				case "compiler":
					if($nvp[1]=="off") $this->compiler = false;
					elseif($nvp[1]=="on") $this->compiler = true;
					break;
				default :
					//Unrecognised nvp, remove from content
					$this->err("Command Not found {$nvp[0]}={$nvp[1]}");
			}
		} else {
			//No parameters, still need to process block commands
			switch($nvp[0]) {
				case "else": case "/else":
					$this->block_eval[$this->block_open] = (($this->block_open > 1) && (!$this->block_eval[$this->block_open-1]))? 
						false : ($this->block_evald[$this->block_open]? false : true);
					break;
				case "endif": case "/endif": case "/if": case "fi":
					$this->block_open--;
					$piece = '';
					if($this->block_open < 0) { 
						//Trap this error - but gracefully
						$this->block_open = 0; 
						$this->block_eval[$this->block_open] = true;
						$this->err("endif without if"); 
					}
					break;
				case "/foreach": case "endfor": case "hcae": case "endeach": case "/each":
					$this->combine_buffers();
					break;
				default:
					$this->err("Command Not found {$nvp[0]} or requires parameter");
					$piece = '';
			}
		}

//		$this->block_trace .= '|' . $this->block_open;
		return $this->buffer($piece);
	}

	protected function assign($piece) {
		$nvp = explode("=", $piece);
		$piece = $this->_getPiece($nvp[1]);
		$this->site->register(substr($nvp[0],1), $piece); return ''; 

	}

	protected function doIf($compare) {
		//piece can contain comparison symbols < > = !
		//If no comparisons found, the test is for [empty] or [0]
		if(substr($compare,0,4)=="func") $compare = $this->_func(substr($compare,5));
		$bo = $this->block_open;
		$this->block_eval[$bo] = 1; //Default to true

		if(strpos($compare, "(") || strpos($compare, ")")) {
			$this->err("Brackets are not supported in IF block evaluations");
			$compare = str_replace("(", "", str_replace(")", "", $compare));
		}

		//split and clean the pieces up...
		$compare = str_replace("===","=#", $compare); 	//Prevents split to triple equals into 3 pieces.
		$compare = str_replace("==","=", $compare); 	//Prevents split to double equals into 3 pieces.
		$compare = str_replace("<=","<#", $compare); 	//Prevents split to lessthan equals into 3 pieces.
		$compare = str_replace(">=",">#", $compare); 	//Prevents split to gtthan equals into 3 pieces.
		$compare = str_replace("!=","!#", $compare); 	//Prevents split to not equals into 3 pieces.

		//Split on AND OR && ||
		$sections = preg_split('/(&&)|(\|\|)|(\bAND\b)|(\bOR\b)/i', $compare, -1, PREG_SPLIT_DELIM_CAPTURE+PREG_SPLIT_NO_EMPTY);

		$eval = "1&&"; $notice = 'IF:';
		for($i=0; $i<count($sections); $i++) {
			if($i%2===0) {
				$comparison = $sections[$i];
				//Handle NOT operands
				$toggle = false;
				if(substr($comparison,0,1)=="!") {
					$comparison = substr($comparison,1);
					$toggle = true;
				}

				$pieces = $this->_cleanParam(preg_split('/[<=\!>]/', $comparison));

				if(!isset($pieces[1])) {
					//No comparisons - just check for nulls/false
					if(empty($pieces[0]))			$this->block_eval[$bo] = false;
					elseif(is_array($pieces[0])) 	$this->block_eval[$bo] = count($pieces[0]);			//Array count
					elseif(is_bool($pieces[0]))		$this->block_eval[$bo] = $pieces[0];				//Boolean value
					elseif(is_numeric($pieces[0])) 	$this->block_eval[$bo] = ($pieces[0] > 0);			//Number
					else 							$this->block_eval[$bo] = !empty($pieces[0]);		//String (Empty?)
				} else {
					$pieces[1] = str_replace("#","",$pieces[1]);
					$notice .= " '{$pieces[0]}' ";

					if(strpos($comparison, '>#')) 		{ $this->block_eval[$bo] = ($pieces[0] >= $pieces[1]); $notice .= '>='; } 
					elseif(strpos($comparison, '>')) 	{ $this->block_eval[$bo] = ($pieces[0] > $pieces[1]); $notice .= '&gt;'; }
					elseif(strpos($comparison, '<#')) 	{ $this->block_eval[$bo] = ($pieces[0] <= $pieces[1]); $notice .= '&lt;='; }
					elseif(strpos($comparison, '<')) 	{ $this->block_eval[$bo] = ($pieces[0] < $pieces[1]); $notice .= '&lt;'; }
					elseif(strpos($comparison, '!#')) 	{ $this->block_eval[$bo] = ($pieces[0] != $pieces[1]); $notice .= '!='; }
					elseif(strpos($comparison, '=#')) 	{ $this->block_eval[$bo] = ($pieces[0] === $pieces[1]); $notice .= '==='; }
					elseif(strpos($comparison, '=')) 	{ $this->block_eval[$bo] = ($pieces[0] == $pieces[1]); $notice .= '=='; }
					$notice .= " '{$pieces[1]}'";
				}

				$eval .= $this->block_eval[$bo]? ($toggle? '0':'1') : ($toggle? '1' : '0');

			} else {
				$sections[$i] = str_replace("and","&&", strtolower(trim($sections[$i])));
				$sections[$i] = str_replace("or","||", strtolower(trim($sections[$i])));
				$notice .= ' ' . $sections[$i];
				$eval .= $sections[$i];
			}
		}

		eval("\$return=" . $eval . ";");
		$this->block_eval[$bo] = $return;

		//To return true, the parent must be true as well.
		if($this->block_open > 0) {if(!$this->block_eval[$this->block_open-1]) $this->block_eval[$this->block_open] = false;}
		if($this->block_eval[$this->block_open]) $this->block_evald[$this->block_open] = true; //Remember the value for next elseif
		$notice .= (($this->block_eval[$this->block_open])? " :=true" : " :=false");

//if($_SERVER['REMOTE_ADDR']=='212.159.21.100') {
//	echo "$compare , $notice<BR>";
//}

		//For debugging IF statements, uncomment this next line
		//$this->site->addCmt($notice);
	}

	protected function _func($nvp) {
		$piece = '';

		//Split the parameter up...
		if($pos = strpos($nvp, "(")) {
			$cmd = substr($nvp,0,$pos);
			$nvp = preg_replace("/\)$/","",$nvp);
			$nvp = trim(substr($nvp, $pos+1));
		} 

		$uncleaned_params = explode(",",$nvp);				//Some functions use this
		if($cmd=="array") {									//Assigning an array
			if(strpos($nvp, ":")) {						//An associative array at that.
				$tmp = array();
				foreach($uncleaned_params as $up) {
					$keys = explode(":", $up);
					$tmp[$keys[0]] = $keys[1];
				}
				return $tmp;
			} else return $uncleaned_params;				//Non-associative, just return uncleaned.
		}

		$params = $this->_cleanParam($uncleaned_params);	//Some use this

		switch($cmd) {
			case "print_r": case "printr": case "printd":
				$piece = '<pre style="max-height:200px;overflow:auto;border:1px solid #AAA;margin:0;padding:0;">'.print_r($params,true).'</pre>';;
				break;
			case "datecmp" : 
				if(!isset($params[1])) return $this->err("Wrong parameter count for datecmp");
				$date1 = strtotime($params[0]);
				$date2 = strtotime($params[1]);
				if($date1===$date2) $piece = 0;
				else $piece = ($date1 > $date2)? 1 : -1;
				break;
			case "strcmp":
				if(!isset($params[1])) return $this->err("Wrong parameter count for strcmp");
				$piece = strcmp($params[0], $params[1]);
				break;
			case "round": 
				$params[1] = isset($params[1])? $params[1] : 0;
				$piece = round($params[0], $params[1]); 
				break;
			case "math":
				$arr = preg_split("/([\*\+\/\-\^\%])/", $uncleaned_params[0], -1, PREG_SPLIT_DELIM_CAPTURE);
				for($i = 0; $i<count($arr); $i++) {
					$arr[$i] = trim($arr[$i]);
					if($i%2==0) {
						if(substr($arr[$i],0,1)=="$") $arr[$i] = $this->site->val(substr($arr[$i],1));
						if(!is_numeric($arr[$i])) return $this->err("Non numeric value in math function:$nvp");
					}
				}

				$eq = implode("", $arr);
				eval("\$return=" . $eq . ";");
				if(isset($params[1])) $piece = number_format($return, (int)$params[1],'.','');
				else $piece = isset($return)? $return : '~';
				break;
			case "number_format":
				if(!isset($params[0])||$params[0]==0) return '0.00';
				if(!isset($params[1])) $params[1] = 2;
				if(!isset($params[2])) $params[2] = '.';
				if(!isset($params[3])) $params[3] = '';
				$piece = number_format($params[0],$params[1],$params[2], $params[3]);
				break;
			case "count":
				if(is_array($params[0])) $piece = count($params[0]); else $piece = '0';
				break;
			default:
				//Call any of the Functions class methods
				$piece = call_user_func_array(array('Functions', $cmd), $params);
		}

		return $piece;
	}

	//Private helper methods
	private function _getPiece($piece) {
		//What type of piece is this?
		if(substr($piece,0,1) == "$") $piece = $this->site->val(substr(trim($piece),1));		//Variable
		elseif(strpos($piece, "(")) $piece = $this->_func($piece);								//Function
		return $piece;	
	}

	private function &_cleanParam($params) {
		//Clean up the params - must be an array or will error
		$newparams = array(); $params = array_filter($params); //Get rid of empty values
		foreach($params as $param) $newparams[] = $this->_getPiece(str_replace("->","::",str_replace("'","",str_replace('"',"",str_replace(" ","",$param)))));
		return $newparams;
	}

	protected function includeFile($filename, $compile=true) {
		$filename = str_replace('"',"",str_replace("'","",$filename));
		if(!file_exists($filename)) {
			//See if we can find the file
			if(file_exists(DIR_FS_TPL."$filename")) $filename = DIR_FS_TPL."$filename";
			elseif(defined("DIR_FS_CDN")) {
				if(file_exists(DIR_FS_CDN."tpl/$filename")) $filename = DIR_FS_CDN."tpl/$filename";
				elseif(file_exists(DIR_FS_CDN."$filename")) $filename = DIR_FS_CDN."$filename";
				elseif(file_exists(DIR_FS_CDN."tpl/".str_replace(DIR_FS_TPL, "", $filename))) $filename = DIR_FS_CDN."tpl/".str_replace(DIR_FS_TPL, "", $filename);
				elseif(file_exists(DIR_FS_CDN.str_replace(DIR_FS_TPL, "", $filename))) $filename = DIR_FS_CDN.str_replace(DIR_FS_TPL, "", $filename);
			}
		}

		if(!file_exists($filename)) return $this->err("File not found $filename");
		else $this->err("[INCLUDE] $filename",false);
		ob_start();
		include($filename);
		$return = ob_get_contents();
		ob_end_clean();
		if(!$compile) return $return;

		//Need to compile the included file too...
		$mc = new Compiler($return);
		return $mc->content();
	}

	protected function minify($in,$type='') {
		switch($type) {
			case 'css': return "<style>$in</style>";
			case 'js': return "<script>$in</script>";
			default: return $in;
		}
	}

	public function __toString() { return $this->content(); }

	protected function err($msg,$log=true) {
		if(!is_bool($log)) $log = true;

		if(strlen($this->tplfilename) < 3) $file = 'struct:'.str_replace("\r\n","",str_replace("\t","",substr($this->_content,0,30)));
		else $file = 'file:'.$this->tplfilename;

		if($log) trigger_error("$msg in $file");
		if(!is_bool($log)) return $log;
		return false;
	}
}


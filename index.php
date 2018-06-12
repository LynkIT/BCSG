<?php
const DIR_FS_ROOT = "banking/";
const DIR_FS_COMMON	= "common/";
const COOKIE_NAME = 'banking';

/* PATHS */
define("DIR_FS_CLASSES", 	DIR_FS_ROOT."classes/");
define("DIR_FS_MODULES", 	DIR_FS_ROOT."modules/");
define("DIR_FS_TPL", 		DIR_FS_ROOT."theme/default/tpl/");
define("DIR_FS_UPLOADS",	DIR_FS_ROOT."uploads/");
define("DIR_FS_LOGS",		DIR_FS_ROOT."logs/");

/* LOADERS - Bring in the framework */
require(DIR_FS_COMMON."scripts/debug.php");
require(DIR_FS_CLASSES."class.registry.php");
require(DIR_FS_CLASSES."class.session.php");
require(DIR_FS_CLASSES."class.theme.php");
require(DIR_FS_CLASSES."class.func.php");
require(DIR_FS_MODULES."parent.module.php");

/* Setup some system variables and inline debugger */
date_default_timezone_set("Europe/London");
register_shutdown_function("fatal_handler");
session_start();

//Invoke the registry
$site = Registry::getInstance();

//Load in the requested module...
$mcode = Functions::getModuleCodeFromURI();
if(file_exists(DIR_FS_MODULES."module.".$mcode.".php")) { 
	require(DIR_FS_MODULES."module.".$mcode.".php");
	$page = new Module($mcode);
	$page->init();
}
<?php
class SuperModule {
	protected $_stdout;
	protected $act;
	protected $params;
	protected $site;
	protected $id;

	public function __construct($qs) {
		$this->id		= $qs;
		$this->act 		= isset($_GET['act'])? $_GET['act'] : 'display';
		$this->params 	= explode(",",$this->act);
		$this->site		= Registry::getInstance();
	}

	protected function getTheme() {
		$theme = new Theme();
		$theme->assign("params",$this->params);
		return $theme;
	}

	protected function err($err='oops') {
		$arr = array(
			'error' => 1,
			'msg' => $err
		);
		echo json_encode($arr);
		return false;
	}

	protected function success($msg='Saved') {
		$arr = array(
			'error' => 0,
			'msg' => $msg
		);
		echo json_encode($arr);
		return true;
	}
}
<?php
class Module extends SuperModule {
	protected $session;
	protected $cardlist = array();

	public function init() {
		//Initialise the cardlist session, start with empty array or use test data.
		$this->session = new Session('cards',array());

		//Options in here to invoke different behaviours or activities
		switch($this->params[0]) {
			case 'addcard': $this->addCard(); break;
			case 'getcards': $this->getCards(); break;
			case 'uploadcsv': $this->uploadCSV(); break;
			default: $this->display();
		}
	}

	protected function addCard() {
		//Assume that no duplicate card numbers should be entered.
		if(strlen($_POST['cardnum']) < 16) return $this->err("Card number is invalid!\nNumbers must be 16 or more characters");
		if($this->cardExists($_POST['cardnum'])) return $this->err("Card exists already!");

		$cards = $this->session->get('cards');
		$cards[] = array(
			'bank'		=> $_POST['bank'],
			'cardnum'	=> $_POST['cardnum'],
			'expiry'	=> "{$_POST['year']}-".str_pad($_POST['month'],2,'0',STR_PAD_LEFT)."-01"
		);

		$cards = $this->session->set('cards',$cards);
		$this->session->save();

		return $this->success("Saved");
	}

	protected function getCards() {
		//Retrieve a list of cards and display the cards table

		//Get the cards list from the current session
		$cards = $this->session->get('cards');

		//Sort the card array into expiry date order, hash the card number and convert the date format
		if(is_array($cards)) {
			usort($cards,array($this,'cardSort'));

			//Hash the card numbers
			foreach($cards as $id=>$card) {
				$hash  = substr($card['cardnum'],0,4);
				$hash .= preg_replace("/[0-9]/","x",substr($card['cardnum'],4));
				$cards[$id]['cardhash'] = $hash;
				$cards[$id]['expiryshort'] = date("M-Y",strtotime($card['expiry']));
			}
		}

		$theme = $this->getTheme();
		$theme->assign("cards",$cards);
		$theme->display(DIR_FS_TPL."home/cardlist.tpl");
	}

	protected function uploadCSV() {
		if(empty($_FILES['uploadfile_1']['name'])) return $this->err("No file attached!");
		if(!is_writeable(DIR_FS_UPLOADS)) return $this->err("Asserts folder is not writeable!");

		$name = $this->sanitize($_FILES['uploadfile_1']['name']);
		$type = $_FILES['uploadfile_1']['type'];
		$size = $_FILES['uploadfile_1']['size'];
		if(move_uploaded_file($_FILES['uploadfile_1']['tmp_name'],DIR_FS_UPLOADS.$name)) {
			//Successfully uploaded a CSV file, populate bank cards
			return $this->extractCSV(DIR_FS_UPLOADS.$name);
		}

		return $this->err("Could not store file");
	}

	protected function extractCSV($filename) {
		//Load in a CSV and extract the data, store it in the cards session.
		if(!file_exists($filename)) return $this->err("File not found: $filename");
		if(!$data = explode("\n",file_get_contents($filename))) return $this->err("No records could be extracted from $filename");

		//Iterate through the imported card list and add it to the list of stored cards
		$cards = $this->session->get('cards');
		foreach($data as $row) {
			$parts = explode(",",$row);

			//Some simple row level validation - check card does not exist and card number is of a minimum length
			if(!isset($parts[2]) || $this->cardExists($parts[1])) continue;
			if(strlen($parts[1]) < 16) continue;

			$parts[2] = Functions::dater($parts[2]); //Convert Mon-YYYY into SQL style date format for easier sorting
			$cards[] = array(
				'bank' 		=> $parts[0],
				'cardnum' 	=> $parts[1],
				'expiry' 	=> $parts[2],
			);
		}

		$this->session->set('cards',$cards);
		$this->session->save();
		return $this->success("Stored");
	}

	protected function display() {
		//Display the main page structure
		//Generate a list of valid expiry dates to populate the add new card form - 10 years into the future
		$yrs = array();
		for($i=0;$i<10;$i++) $yrs[] = (int)date("Y")+$i;

		//Invoke the theme and display the opening page.
		$theme = $this->getTheme();
		$theme->assign("yrs",$yrs);
		$theme->display(DIR_FS_TPL."home/home.tpl");
	}

	private function cardSort($a,$b) {
		return $a['expiry'] > $b['expiry']? -1 : 1;
	}

	private function cardExists($cardnum) {
		if(empty($this->cardlist) && is_array($this->session->get('cards'))) {
			//Decompile the card list for easier duplicate checking, only do this once for performance reasons
			foreach($this->session->get('cards') as $card) {
				$this->cardlist[$card['cardnum']] = $card['bank'];
			}
		}

		return isset($this->cardlist[$cardnum])? true : false;
	}

	private function sanitize($filename) {
		//Intended to sanitize the filename, currently just returns as is
		return $filename;
	}
}
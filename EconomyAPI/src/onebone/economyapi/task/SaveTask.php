<?php

namespace onebone\economyapi\task;

use onebone\economyapi\EconomyAPI;

use pocketmine\scheduler\AsyncTask;

class SaveTask extends AsyncTask{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin){
		$this->plugin = $plugin;
	}
	
	public function onRun(){
		$this->plugin->save();
	}
}
<?php

namespace onebone\economyapi\task;

use onebone\economyapi\EconomyAPI;

use pocketmine\scheduler\PluginTask;

class SaveTask extends PluginTask{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin){
		parent::__construct($plugin);
	}
	
	public function onRun($currentTick){
		$this->getOwner()->save();
	}
}
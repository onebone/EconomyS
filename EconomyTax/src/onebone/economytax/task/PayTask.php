<?php

namespace onebone\economytax\task;

use pocketmine\scheduler\PluginTask;

use onebone\economytax\EconomyTax;

class PayTask extends PluginTask{
	public function __construct(EconomyTax $plugin){
		parent::__construct($plugin);
	}
	
	public function onRun($currentTick){
		$this->getOwner()->payTax();
	}
}
<?php

namespace onebone\economyshop\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;

class PreShopCreationEvent extends PluginEvent{
	private $owner, $sign;
	
	public function __construct(Player $owner){
		$this->owner = $owner;
	}
	
	public function getOwner(){
		return $this->owner;
	}
	
	public function getSign(){
		return $this->sign;
	}
}
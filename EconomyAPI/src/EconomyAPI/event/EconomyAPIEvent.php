<?php

namespace economyapi\event;

use pocketmine\plugin\Plugin;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;

use economyapi\EconomyAPI;

class EconomyAPIEvent extends PluginEvent implements Cancellable{
	public function __construct(EconomyAPI $plugin, $issuer){
		$this->plugin = $plugin;
	}
	
	public function getIssuer(){
		return $this->issuer;
	}
}
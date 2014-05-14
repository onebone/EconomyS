<?php

namespace EconomyAPI\event;

use pocketmine\plugin\Plugin;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;

class EconomyAPIEvent extends PluginEvent implements Cancellable{
	public function __construct(\EconomyAPI\EconomyAPI $plugin, $issuer){
		$this->plugin = $plugin;
	}
	
	public function getIssuer(){
		return $this->issuer;
	}
}
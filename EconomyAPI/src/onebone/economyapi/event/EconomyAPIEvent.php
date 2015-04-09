<?php

namespace onebone\economyapi\event;

use pocketmine\plugin\Plugin;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;

use onebone\economyapi\EconomyAPI;

class EconomyAPIEvent extends PluginEvent implements Cancellable{
	private $issuer;
	
	public function __construct(EconomyAPI $plugin, $issuer){
		parent::__construct($plugin);
		$this->issuer = $issuer;
	}
	
	public function getIssuer(){
		return $this->issuer;
	}
}
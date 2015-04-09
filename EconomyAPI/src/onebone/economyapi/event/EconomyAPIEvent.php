<?php

namespace onebone\economyapi\event;

use pocketmine\plugin\Plugin;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;

use onebone\economyapi\EconomyAPI;

class EconomyAPIEvent extends PluginEvent implements Cancellable{
	public function __construct(EconomyAPI $plugin){
		parent::__construct($plugin);
	}
}
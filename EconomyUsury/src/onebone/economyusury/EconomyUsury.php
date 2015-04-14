<?php

namespace onebone\economyusury;

use pocketmine\plugin\PluginBase;

use onebone\economyusury\commands\UsuryCommand;

class EconomyUsury extends PluginBase{
	public function onEnable(){
		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->register("usury", new UsuryCommand("usury", $this));
	}
}
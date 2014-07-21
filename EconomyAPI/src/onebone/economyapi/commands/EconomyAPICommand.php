<?php

namespace onebone\economyapi\commands;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;

use onebone\economyapi\EconomyAPI;

abstract class EconomyAPICommand extends Command implements PluginIdentifiableCommand{
	private $owingPlugin;
	
	public function __construct($name, EconomyAPI $plugin){
		parent::__construct($name);
		$this->owningPlugin = $plugin;
		$this->usageMessage = "";
	}

	public function getPlugin(){
		return $this->owningPlugin;
	}
}
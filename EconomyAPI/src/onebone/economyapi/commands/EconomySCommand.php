<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class EconomySCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "economys"){
		parent::__construct($plugin, $cmd);
		$this->setPermission("economyapi.command.economys");
		$this->setDescription("Shows plugin list compatible with EconomyAPI");
		$this->setUsage("/$cmd");
	}
	
	public function exec(CommandSender $sender, array $params){
		$output = "Showing list :\n";
		foreach($this->getPlugin()->getList() as $plugin){
			$output .= $plugin.", ";
		}
		$output = substr($output, 0, -2);
		$sender->sendMessage($output);
		return true;
	}
}
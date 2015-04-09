<?php

namespace onebone\economyapi\commands;

use onebone\economyapi\EconomyAPI;

use pocketmine\command\CommandSender;

class MyDebtCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "mydebt"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd");
		$this->setPermission("economyapi.command.mydebt");
		$this->setDescription("Shows my debt");
	}
	
	public function exec(CommandSender $sender, array $params){
		$mine = $this->getPlugin()->myDebt($sender);
		$sender->sendMessage($this->getPlugin()->getMessage("mydebt-mydebt", $sender->getName(), array($mine, "%2", "%3", "%4")));
		return true;
	}
}
<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class MyMoneyCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "mymoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd");
		$this->setDescription("Shows your money");
		$this->setPermission("economyapi.command.mymoney");
	}
	
	public function exec(CommandSender $sender, array $args){
		$username = $sender->getName();
		$result = $this->getPlugin()->myMoney($username);
		$sender->sendMessage($this->getPlugin()->getMessage("mymoney-mymoney", $sender->getName(), array($result, "%2", "%3", "%4")));
        return true;
	}
}
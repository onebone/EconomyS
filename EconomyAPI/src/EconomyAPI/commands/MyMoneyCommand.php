<?php

namespace economyapi\commands;

use economyapi\EconomyAPI;

class MyMoneyCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(EconomyAPI $api, $cmd = "mymoney"){
		parent::__construct($cmd, $api);
		$this->setUsage("/$cmd");
		$this->setDescription("Shows your money");
		$this->setPermission("economyapi.command.mymoney");
	}
	
	public function execute(\pocketmine\command\CommandSender $sender, $label, array $args){
		if(!$this->getPlugin()->isEnabled()){
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		
		if(!$sender instanceof \pocketmine\Player){
			$sender->sendMessage("Please run this command in-game");
			return true;
		}
		$username = $sender->getName();
		$result = $this->getPlugin()->myMoney($username);
		$sender->sendMessage($this->getPlugin()->getMessage("mymoney-mymoney", $sender->getName(), array($result, "%2", "%3", "%4")));
	}
}
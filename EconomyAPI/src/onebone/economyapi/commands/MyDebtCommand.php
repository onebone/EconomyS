<?php

namespace onebone\economyapi\commands;

use onebone\economyapi\EconomyAPI;

use pocketmine\command\CommandSender;
use pocketmine\Player;

class MyDebtCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin, $cmd = "mydebt"){
		parent::__construct($cmd, $plugin);
		$this->plugin = $plugin;
		$this->setUsage("/$cmd");
		$this->setPermission("economyapi.command.mydebt");
		$this->setDescription("Shows my debt");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->plugin->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game,");
			return true;
		}
		
		$mine = $this->plugin->myDebt($sender);
		
		$sender->sendMessage($this->plugin->getMessage("mydebt-mydebt", $sender->getName(), array($mine, "%2", "%3", "%4")));
		return true;
	}
}
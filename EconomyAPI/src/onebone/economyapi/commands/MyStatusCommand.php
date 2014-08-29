<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class MyStatusCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "mystatus"){
		parent::__construct($cmd, $plugin);
		$this->setUsage("/$cmd");
		$this->setDescription("Shows your money status");
		$this->setPermission("economyapi.command.mystatus");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game.");
			return true;
		}
		
		$money = $this->getPlugin()->getAllMoney();
		
		$allMoney = 0;
		foreach($money["money"] as $m){
			$allMoney += $m;
		}
		$topMoney = (($money["money"][$sender->getName()] / $allMoney) * 100);
		$allDebt = 0;
		foreach($money["debt"] as $d){
			$allDebt += $d;
		}
		$topDebt = (($money["debt"][$sender->getName()] / $allDebt) * 100);
		$sender->sendMessage($this->getPlugin()->getMessage("mystatus-show", $sender->getName(), array($topMoney, $topDebt, "%3", "%4")));
		return true;
	}
}

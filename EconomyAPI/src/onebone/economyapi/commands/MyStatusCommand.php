<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class MyStatusCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "mystatus"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd");
		$this->setDescription("Shows your money status");
		$this->setPermission("economyapi.command.mystatus");
	}
	
	public function exec(CommandSender $sender, array $params){
		$money = $this->getPlugin()->getAllMoney();
		
		$allMoney = 0;
		foreach($money["money"] as $m){
			$allMoney += $m;
		}
		$topMoney = 0;
		if($allMoney > 0){
			$topMoney = round((($money["money"][$sender->getName()] / $allMoney) * 100), 2);
		}
		$allDebt = 0;
		foreach($money["debt"] as $d){
			$allDebt += $d;
		}
		$topDebt = 0;
		if($allDebt > 0){
			$topDebt = round((($money["debt"][$sender->getName()] / $allDebt) * 100), 2);
		}
		$sender->sendMessage($this->getPlugin()->getMessage("mystatus-show", $sender->getName(), array($topMoney, $topDebt, "%3", "%4")));
		return true;
	}
}

<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class SetMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "setmoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <money>");
		$this->setDescription("Sets player's money");
		$this->setPermission("economyapi.command.setmoney");
	}
	
	public function exec(CommandSender $sender, array $args){
		$player = array_shift($args);
		$money = array_shift($args);
		
		if(trim($player) === "" or trim($money) === ""){
			$sender->sendMessage("Usage: /".$this->getName()." <player> <money>");
			return true;
		}
		
		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		$result = $this->getPlugin()->setMoney($player, $money, "SetMoneyCommand");
		$output = "";
		switch($result){
			case -2:
			$output .= $this->getPlugin()->getMessage("setmoney-failed", $sender->getName());
			break;
			case -1:
			$output .= $this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
			break;
			case 0:
			$output .= $this->getPlugin()->getMessage("setmoney-invalid-number", $sender->getName(), array($money, "%2", "%3", "%4"));
			break;
			case 1:
			$output .= $this->getPlugin()->getMessage("setmoney-setmoney", $sender->getName(), array($player, $money, "%3", "%4"));
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
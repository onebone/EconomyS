<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class SeeMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "seemoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player>");
		$this->setDescription("See player's money");
		$this->setPermission("economyapi.command.seemoney");
	}
	
	public function exec(CommandSender $sender, array $args){
		$player = array_shift($args);
		if(trim($player) === ""){
			$sender->sendMessage("Usage: /".$this->getName()." <player>");
			return true;
		}
		
		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		$result = $this->getPlugin()->myMoney($player);
		if($result === false){
			$sender->sendMessage($this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4")));
			return true;
		}else{
			$sender->sendMessage($this->getPlugin()->getMessage("seemoney-seemoney", $sender->getName(), array($player, $result, "%3", "%4")));
			return true;
		}
	}
}
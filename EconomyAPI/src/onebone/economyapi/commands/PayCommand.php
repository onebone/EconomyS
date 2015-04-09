<?php

namespace onebone\economyapi\commands;

use pocketmine\Server;
use pocketmine\command\CommandSender;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class PayCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "pay"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.pay");
		$this->setDescription("Pay or give the money to the others");
	}
	
	public function exec(CommandSender $sender, array $params){
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: ".$this->getUsage());
			return true;
		}
		
		$server = Server::getInstance();
		//  Player finder  //
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		$result = $this->getPlugin()->reduceMoney($sender, $amount);
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage($this->getPlugin()->getMessage("pay-failed", $sender));
			return true;
		}
		$result = $this->getPlugin()->addMoney($player, $amount);
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage($this->getPlugin()->getMessage("request-cancelled", $sender));
            $this->getPlugin()->addMoney($sender, $amount, true);
			return true;
		}
		$sender->sendMessage($this->getPlugin()->getMessage("pay-success", $sender, [$amount, $player, "%3", "%4"]));
        return true;
	}
}
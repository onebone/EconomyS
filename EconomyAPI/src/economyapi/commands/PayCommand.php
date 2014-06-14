<?php

namespace economyapi\commands;

use pocketmine\Server;

use economyapi\EconomyAPI;

class PayCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $plugin, $cmd = "pay"){
		parent::__construct($cmd, $plugin);
		$this->cmd = $cmd;
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.pay");
		$this->setDescription("Pay or give the money to the others");
	}
	
	public function execute(\pocketmine\command\CommandSender $sender, $label, array $params){
		$plugin = $this->getPlugin();
		if(!$plugin->isEnabled()){
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game");
			return true;
		}
		
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: /".$this->cmd." <player> <money>");
			return true;
		}
		
		$server = Server::getInstance();
		//  Player finder  //
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		/*$playerArr = $server->matchPlayer($player);
		if(count($playerArr) === 0){
			if($plugin->accountExists($player)){
				$target = $player;
			}else{
				$sender->sendMessage($plugin->getMessage("player-not-connected", $sender->getName(), array($player, "%2", "%3", "%4")));
				return true;
			}
		}else{
			$target = $playerArr[0];
		}*/
		
		$result = $plugin->reduceMoney($sender, $amount);
		if($result !== \economyapi\EconomyAPI::RET_SUCCESS){
			$sender->sendMessage("Your request have been denied");
			return true;
		}
		$result = $plugin->addMoney($target, $amount);
		if($result !== \economyapi\EconomyAPI::RET_SUCCESS){
			$sender->sendMessage("Your request have been denied");
			$plugin->addMoney($target, $amount, true);
			return true;
		}
	}
}
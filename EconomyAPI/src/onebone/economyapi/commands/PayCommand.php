<?php

namespace onebone\economyapi\commands;

use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class PayCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin, $cmd = "pay"){
		parent::__construct($cmd, $plugin);
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
		
		$result = $plugin->reduceMoney($sender, $amount);
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage("Your request have been denied");
			return true;
		}
		$result = $plugin->addMoney($target, $amount);
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage("Your request have been denied");
			$plugin->addMoney($target, $amount, true);
			return true;
		}
	}
}
<?php

namespace onebone\economyapi\commands;

use pocketmine\Server;
use pocketmine\command\CommandSender;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class PayCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin, $cmd = "pay"){
		parent::__construct($cmd, $plugin);
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.pay");
		$this->setDescription("Pay or give the money to the others");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
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
			$sender->sendMessage($plugin->getMessage("pay-failed", $sender));
			return true;
		}
		$result = $plugin->addMoney($player, $amount);
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage($plugin->getMessage("request-cancelled", $sender));
			$plugin->addMoney($sender, $amount, true);
			return true;
		}
		$sender->sendMessage($plugin->getMessage("pay-success", $sender, [$amount, $player, "%3", "%4"]));
	}
}
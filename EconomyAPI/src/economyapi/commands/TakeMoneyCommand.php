<?php

namespace economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

use economyapi\EconomyAPI;

class TakeMoneyCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $plugin, $cmd = "takemoney"){
		parent::__construct($cmd, $plugin);
		$this->cmd = $cmd;
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.takemoney");
		$this->setDescription("Take others' money");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: /".$this->cmd." <player> <amount>");
			return true;
		}
		
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("takemoney-invalid-number", $sender->getName()));
			return true;
		}
		
		/*$p = $this->getPlugin()->getServer()->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}else{
			$result = $this->getPlugin()->accountExists($player);
			if($result === false){
				$sender->sendMessage($this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4")));
				return true;
			}
		}*/
		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		$result = $this->getPlugin()->reduceMoney($player, $amount, false, "TakeMoneyCommand");
		$output = "";
		switch($result){
			case EconomyAPI::RET_SUCCESS:
			$output .= $this->getPlugin()->getMessage("takemoney-took-money", $sender->getName(), array($player, $amount, "%3", "%4"));
			break;
			case EconomyAPI::RET_INVALID:
			$output .= $this->getPlugin()->getMessage("takemoney-player-lack-of-money", $sender->getName(), array($player, $amount, $this->getPlugin()->myMoney($player), "%4"));
			break;
			default:
			$output .= $this->getPlugin()->getMessage("takemoney-failed", $sender->getName());
		}
		$sender->sendMessage($output);
	}
}
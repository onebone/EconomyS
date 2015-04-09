<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class BankAdminCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "bankadmin"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <takemoney|givemoney> <player> <amount>");
		$this->setDescription("Manages players' bank account");
		$this->setPermission("economyapi.command.bankadmin");
	}
	
	public function exec(CommandSender $sender, array $params){
		$sub = array_shift($params);
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($sub) === "" or trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: ".$this->usageMessage);
			return true;
		}
		
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("bank-takemoney-must-bigger-than-zero", $sender->getName()));
			return true;
		}
		
		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		switch($sub){
			case "takemoney":
			$result = $this->getPlugin()->reduceBankMoney($player, $amount, true, "BankAdminCommand");
			$output = "";
			switch($result){
				case EconomyAPI::RET_INVALID:
				$output .= $this->getPlugin()->getMessage("bank-takemoney-no-money", $sender->getName(), array($player, $amount, $this->getPlugin()->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_SUCCESS:
				$output .= $this->getPlugin()->getMessage("bank-takemoney-done", $sender->getName(), array($player, $amount, $this->getPlugin()->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_NOT_FOUND:
				$output .= $this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
				break;
			}
			$sender->sendMessage($output);
			break;
			case "givemoney":
			$result = $this->getPlugin()->addBankMoney($player, $amount, true, "BankAdminCommand");
			$output = "";
			switch($result){
				case EconomyAPI::RET_INVALID:
				$output .= $this->getPlugin()->getMessage("bank-givemoney-must-bigger-than-zero", $sender->getName());
				break;
				case EconomyAPI::RET_SUCCESS:
				$output .= $this->getPlugin()->getMessage("bank-givemoney-done", $sender->getName(), array($player, $amount, $this->getPlugin()->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_NOT_FOUND:
				$output .= $this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
				break;
			}
			$sender->sendMessage($output);
			break;
			default:
			$sender->sendMessage("Usage: ".$this->usageMessage);
		}
		return true;
	}
}
<?php

namespace EconomyAPI\commands;

use pocketmine\command\CommandSender;
use pocketmine\Server;

use EconomyAPI\EconomyAPI;

class BankAdminCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(EconomyAPI $plugin, $cmd = "bankadmin"){
		parent::__construct($cmd, $plugin);
		$this->setUsage("/$cmd <takemoney|givemoney> <player> <amount>");
		$this->setDescription("Manages players' bank account");
		$this->setPermission("economyapi.command.bankadmin");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		$plugin = $this->getPlugin();
		if(!$plugin->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		$sub = array_shift($params);
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($sub) === "" or trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: ".$this->usageMessage);
			return true;
		}
		
		if($amount <= 0){
			$sender->sendMessage($plugin->getMessage("bank-takemoney-must-bigger-than-zero", $sender->getName()));
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
			$result = $plugin->reduceBankMoney($player, $amount, true, "BankAdminCommand");
			$output = "";
			switch($result){
				case EconomyAPI::RET_INVALID:
				$output .= $plugin->getMessage("bank-takemoney-no-money", $sender->getName(), array($player, $amount, $plugin->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_SUCCESS:
				$output .= $plugin->getMessage("bank-takemoney-done", $sender->getName(), array($player, $amount, $plugin->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_NOT_FOUND:
				$output .= $plugin->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
				break;
			}
			$sender->sendMessage($output);
			break;
			case "givemoney":
			$result = $plugin->addBankMoney($player, $amount, true, "BankAdminCommand");
			$output = "";
			switch($result){
				case EconomyAPI::RET_INVALID:
				$output .= $plugin->getMessage("bank-givemoney-must-bigger-than-zero", $sender->getName());
				break;
				case EconomyAPI::RET_SUCCESS:
				$output .= $plugin->getMessage("bank-givemoney-done", $sender->getName(), array($player, $amount, $plugin->myBankMoney($player), "%4"));
				break;
				case EconomyAPI::RET_NOT_FOUND:
				$output .= $plugin->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
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
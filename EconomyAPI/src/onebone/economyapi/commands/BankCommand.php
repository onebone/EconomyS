<?php

namespace onebone\economyapi\commands;

use onebone\economyapi\EconomyAPI;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

class BankCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "bank"){
		parent::__construct($plugin, $cmd);
		$this->setPermission("economyapi.command.bank");
		$this->setDescription("Command for controlling bank account");
		$this->setUsage("/$cmd <deposit|withdraw|seemoney|mymoney>");
	}
	
	public function exec(CommandSender $sender, array $params){
		$sub = array_shift($params);
		$amount = array_shift($params);
		
		switch($sub){
			case "deposit":
			if(trim($amount) === "" or !is_numeric($amount)){
				$sender->sendMessage("Usage: /".$this->getName()." deposit <amount>");
				return true;
			}
			
			$money = $this->getPlugin()->myMoney($sender->getName());
			
			if($money < $amount){
				$sender->sendMessage($this->getPlugin()->getMessage("bank-deposit-dont-have-money", $sender->getName(), array($amount, $money, "%3", "%4")));
				return true;
			}
			
			$this->getPlugin()->reduceMoney($sender->getName(), $amount, true); // Reduce money in force
			$result = $this->getPlugin()->addBankMoney($sender->getName(), $amount, true);
			if($result === EconomyAPI::RET_SUCCESS){
				$sender->sendMessage($this->getPlugin()->getMessage("bank-deposit-success", $sender->getName(), array($amount, "%2", "%3", "%4")));
			}else{
				$sender->sendMessage($this->getPlugin()->getMessage("bank-deposit-failed", $sender->getName()));
			}
			break;
			case "withdraw":
			if(trim($amount) === "" or !is_numeric($amount)){
				$sender->sendMessage("Usage: /".$this->getName()." withdraw <amount>");
				return true;
			}
			
			$money = $this->getPlugin()->myBankMoney($sender->getName());
			
			if($money < $amount){
				$sender->sendMessage($this->getPlugin()->getMessage("bank-withdraw-lack-of-credit", $sender->getName(), array($amount, $money, "%3", "%4")));
				return true;
			}else{
				$this->getPlugin()->reduceBankMoney($sender->getName(), $amount, true);
				$this->getPlugin()->addMoney($sender->getName(), $amount, true);
				$sender->sendMessage($this->getPlugin()->getMessage("bank-withdraw-success", $sender->getName(), array($amount, "%2", "%3", "%4")));
				return true;
			}
			break;
			case "seemoney":
			if(trim($amount) === ""){
				$sender->sendMessage("Usage: /".$this->getName()." seemoney <player>");
				return true;
			}
			
			//  Player finder  //
			$server = Server::getInstance();
			$p = $server->getPlayer($amount);
			if($p instanceof Player){
				$player = $p->getName(); //FIXME: $player not used anywhere
			}
			// END //
			
			$money = $this->getPlugin()->myBankMoney($amount);
			if($money === false){
				$sender->sendMessage($this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($amount, "%2", "%3", "%4")));
			}else{
				$sender->sendMessage($this->getPlugin()->getMessage("bank-hismoney", $sender->getName(), array($amount, $money, "%3", "%4")));
			}
			return true;
			case "mymoney":
			$money = $this->getPlugin()->myBankMoney($sender);
			$sender->sendMessage($this->getPlugin()->getMessage("bank-mymoney", $sender->getName(), array($money, "%2", "%3", "%4")));
			break;
			default:
			$sender->sendMessage("Usage: /".$this->getName()." <deposit|withdraw|seemoney|mymoney>");
		}
		return true;
	}
}
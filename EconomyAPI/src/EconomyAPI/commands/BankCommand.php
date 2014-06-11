<?php

namespace EconomyAPI\commands;

use EconomyAPI\EconomyAPI;

use pocketmine\command\CommandSender;
use pocketmine\Player;

class BankCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $plugin, $cmd = "bank"){
		parent::__construct($cmd, $plugin);
		$this->cmd = $cmd;
		$this->plugin = $plugin;
		$this->setPermission("economyapi.command.bank");
		$this->setDescription("Command for controlling bank account");
		$this->setUsage("/$cmd <deposit|withdraw|seemoney|mymoney>");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->testPermission($sender) or !$this->plugin->isEnabled()){
			return false;
		}
		
		$sub = array_shift($params);
		$amount = array_shift($params);
		
		switch($sub){
			case "deposit":
			if(trim($amount) === "" or !is_numeric($amount)){
				$sender->sendMessage("Usage: /$cmd deposit <amount>");
				return true;
			}
			if(!$sender instanceof Player){
				$sender->sendMessage("Please run this command in-game");
				return true;
			}
			
			$money = $this->plugin->myMoney($sender->getName());
			
			if($money < $amount){
				$sender->sendMessage($this->plugin->getMessage("bank-deposit-dont-have-money", $sender->getName(), array($amount, $money, "%3", "%4")));
				return true;
			}
			
			$this->plugin->reduceMoney($sender->getName(), $amount, true); // Reduce money in force
			$result = $this->plugin->addBankMoney($sender->getName(), $amount, true);
			if($result === EconomyAPI::RET_SUCCESS){
				$sender->sendMessage($this->plugin->getMessage("bank-deposit-success", $sender->getName(), array($amount, "%2", "%3", "%4")));
			}else{
				$sender->sendMessage($this->plugin->getMessage("bank-deposit-failed", $sender->getName()));
			}
			break;
			case "withdraw":
			if(trim($amount) === "" or !is_numeric($amount)){
				$sender->sendMessage("Usage: /$cmd withdraw <amount>");
				return true;
			}
			if(!$sender instanceof Player){
				$sender->sendMessage("Please run this command in-game");
				return true;
			}
			
			$money = $this->plugin->myBankMoney($sender->getName());
			
			if($money < $amount){
				$sender->sendMessage($this->plugin->getMessage("bank-withdraw-lack-of-credit", $sender->getName(), array($amount, $money, "%3", "%4")));
				return true;
			}else{
				$this->plugin->reduceBankMoney($sender->getName(), $amount, true);
				$this->plugin->addMoney($sender->getName(), $amount, true);
				$sender->sendMessage($this->plugin->getMessage("bank-withdraw-success", $sender->getName(), array($amount, "%2", "%3", "%4")));
				return true;
			}
			break;
			case "seemoney":
			if(trim($amount) === ""){
				$sender->sendMessage("Usage: /$cmd seemoney <player>");
				return true;
			}
			
			$money = $this->plugin->myBankMoney($player);
			if($money === false){
				$sender->sendMessage($this->plugin->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4")));
			}else{
				$sender->sendMessage($this->plugin->getMessage("bank-hismoney", $sender->getName(), array($player, $money, "%3", "%4")));
			}
			return true;
			case "mymoney":
			$money = $this->plugin->myBankMoney($sender);
			$sender->sendMessage($this->plugin->getMessage("bank-mymoney", $sender->getName(), array($money, "%2", "%3", "%4")));
			break;
			default:
			$sender->sendMessage("Usage: /".$this->cmd." <deposit|withdraw|seemoney|mymoney>");
		}
	}
}
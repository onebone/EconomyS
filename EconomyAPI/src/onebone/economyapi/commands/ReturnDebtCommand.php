<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class ReturnDebtCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "returndebt"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <amount>");
		$this->setDescription("Returns debt from your account");
		$this->setPermission("economyapi.command.returndebt");
	}
	
	public function exec(CommandSender $sender, array $params){
		$amount = array_shift($params);
		
		if(trim($amount) === "" or (!is_numeric($amount) and $amount !== "all")){
			$sender->sendMessage("Usage: /".$this->getName()." <amount>");
			return true;
		}
		
		if($amount === "all"){
			$amount = $this->getPlugin()->myDebt($sender);
		}
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-must-bigger-than-zero", $sender->getName()));
			return true;
		}
		
		if($this->getPlugin()->myMoney($sender) < $amount){
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-dont-have-money", $sender->getName(), array($amount, $this->getPlugin()->myMoney($sender), "%3", "%4")));
			return true;
		}
		
		$result = $this->getPlugin()->reduceDebt($sender, $amount, false, "ReturnDebtCommand");
		switch($result){
			case EconomyAPI::RET_INVALID:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-dont-have-debt", $sender->getName(), array($amount, $this->getPlugin()->myDebt($sender), "%3", "%4")));
			break;
			case EconomyAPI::RET_CANCELLED:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-failed", $sender->getName()));
			break;
			case EconomyAPI::RET_SUCCESS:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-returndebt", $sender->getName(), array($amount, $this->getPlugin()->myDebt($sender), "%3", "%4")));
			break;
		}
		return true;
	}
}
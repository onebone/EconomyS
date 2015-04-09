<?php

namespace onebone\economyapi\commands;

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class TakeDebtCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "takedebt"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <amount>");
		$this->setPermission("economyapi.command.takedebt");
		$this->setDescription("Takes debt from plugin");

	}
	
	public function exec(CommandSender $sender, array $params){
		$amount = array_shift($params);
		
		if(trim($amount) === "" or !is_numeric($amount)){
			$cmd = $this->getName();
			$sender->sendMessage("Usage: /$cmd <amount>");
			return true;
		}
		$result = $this->getPlugin()->addDebt($sender, $amount, false, "TakeDebtCommand");
		$output = "";
		switch($result){
			case -4: // RET_ERROR_1
			$output .= $this->getPlugin()->getMessage("takedebt-over-range", $sender->getName(), array($amount, $this->getPlugin()->getConfigurationValue("debt-limit"), "%3", "%4"));
			break;
			case -3: // RET_ERROR2
			$output .= $this->getPlugin()->getMessage("takedebt-over-range-once", $sender->getName(), array($amount, $this->getPlugin()->getConfigurationValue("once-debt-limit"), "%3", "%4"));
			break;	
			case -2: // RET_CANCELLED
			$output .= $this->getPlugin()->getMessage("request-cancelled", $sender->getName());
			break;
			case 0: // RET_INVALID
			$output .= $this->getPlugin()->getMessage("takedebt-must-bigger-than-zero", $sender->getName());
			break;
			case 1: // RET_SUCCESS
			$output .= $this->getPlugin()->getMessage("takedebt-takedebt", $sender->getName(), array($amount, "%2", "%3", "%4"));
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
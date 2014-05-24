<?php

namespace EconomyAPI\commands;

use pocketmine\Player;
use pocketmine\command\CommandSender;

use EconomyAPI\EconomyAPI;

class TakeDebtCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $plugin, $cmd = "takedebt"){
		parent::__construct($cmd, $plugin);
		$this->cmd = $cmd;
		$this->setUsage("/$cmd <amount>");
		$this->setPermission("economyapi.command.takedebt");
		$this->setDescription("Takes debt from plugin");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled()){
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game.");
			return true;
		}
		
		$amount = array_shift($params);
		
		if(trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: /$cmd <amount>");
			return true;
		}
		$result = $this->getPlugin()->addDebt($sender, $amount, false, "TakeDebtCommand");
		$output = "";
		switch($result[0]){
			case -4: // RET_ERROR_1
			$output .= $this->getPlugin()->getMessage("takedebt-over-range", array($amount, $sender->getName(), $this->getPlugin()->getConfigurationValue("debt-limit"), "%3", "%4"));
			break;
			case -3: // RET_ERROR2
			$output .= $this->getPlugin()->getMessage("takedebt-over-range-once", array($amount, $sender->getName(), $this->getPlugin()->getConfigurationValue("once-debt-limit"), "%3", "%4"));
			break;	
			case -2: // RET_CANCELLED
			$output .= $this->getPlugin()->getMessage("request-cancelled", $sender->getName());
			break;
			case 0: // RET_INVALID
			$output .= $this->getPlugin()->getMessage("takedebt-must-bigger-than-zero", $sender->getName());
			break;
			case 1: // RET_SUCCESS
			$output .= $this->getPlugin()->getMessage("takedebt-takedebt", array($amount, "%2", "%3", "%4"));
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
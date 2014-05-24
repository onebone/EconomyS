<?php

namespace EconomyAPI\commands;

use pocketmine\command\CommandSender;

use EconomyAPI\EconomyAPI;

class SetMoneyCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $api, $cmd = "setmoney"){
		parent::__construct($cmd, $api);
		$this->cmd = $cmd;
		$this->setUsage("/$cmd <player> <money>");
		$this->setDescription("Sets player's money");
		$this->setPermission("economyapi.command.setmoney");
	}
	
	public function execute(CommandSender $sender, $label, array $args){
		if(!$this->getPlugin()->isEnabled()){
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		
		$player = array_shift($args);
		$money = array_shift($args);
		
		if(trim($player) === "" or trim($money) === ""){
			$sender->sendMessage("Usage: /".$this->cmd." <player> <money>");
			return true;
		}
		
		$result = $this->getPlugin()->setMoney($player, $money, "SetMoneyCommand");
		$output = "";
		switch($result){
			case -2:
			$output .= $this->getPlugin()->getMessage("setmoney-failed", $sender->getName());
			break;
			case -1:
			$output .= $this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
			break;
			case 0:
			$output .= $this->getPlugin()->getMessage("setmoney-invalid-number", $sender->getName(), array($money, "%2", "%3", "%4"));
			break;
			case 1:
			$output .= $this->getPlugin()->getMessage("setmoney-setmoney", $sender->getName(), array($player, $money, "%3", "%4"));
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
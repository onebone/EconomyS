<?php

namespace onebone\economyusury\commands;

use pocketmine\command\PluginCommand;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;

class UsuryCommand extends PluginCommand implements PluginIdentifiableCommand{
	public function __construct($cmd = "usury", $plugin){
		parent::__construct($cmd, $plugin);
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		switch(array_shift($params)){
			case "host":
			switch(array_shift($params)){
				case "open":
				
				break;
				case "close":
				
				break;
			}
			break;
			case "request":
			$requestTo = strtolower(array_shift($params));
			if(trim($requestTo) == ""){
				$sender->sendMessage("Usage: /usury request <host>");
				break;
			}
			
			break;
			default:
			$sender->sendMessage("Usage: ".$this->getUsage());
		}
		return true;
	}
}
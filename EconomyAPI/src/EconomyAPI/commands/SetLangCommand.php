<?php

namespace EconomyAPI\commands;

use pocketmine\command\CommandSender;

class SetLangCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(\EconomyAPI\EconomyAPI $plugin, $cmd = "setlang"){
		parent::__construct($cmd, $plugin);
		$this->cmd = $cmd;
		$this->setUsage("/$cmd <lang>");
		$this->setPermission("economyapi.command.setlang");
		$this->setDescription("Sets language resource");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		$lang = array_shift($params);
		
		if(trim($lang) === ""){
			$sender->sendMessage("Usage : /".$this->cmd." <lang>");
			return true;
		}
		
		$result = $this->getPlugin()->setLang($lang, $sender->getName());
		if($result === false){
			$sender->sendMessage("Requested language does not exist");
		}
	}
}
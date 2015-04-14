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
	}
}
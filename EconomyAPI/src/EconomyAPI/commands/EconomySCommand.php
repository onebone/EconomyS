<?php

namespace EconomyAPI\commands;

use pocketmine\plugin\Plugin;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class EconomySCommand extends EconomyAPICommand{
	private $plugin;
	
	public function __construct(Plugin $plugin, $cmd = "economys"){
		parent::__construct($cmd, $plugin);
		$this->plugin = $plugin;
		$this->setPermission("economyapi.command.economys");
		$this->setDescription("Shows plugin list compatible with EconomyAPI");
		$this->setUsage("/$cmd");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled()){
			return false;
		}
		if(!$this->testPermission($sender)){
			return false;
		}
		$output = "Showing list :\n";
		foreach($this->getPlugin()->getList() as $plugin){
			$output .= $plugin.", ";
		}
		$output = substr($output, 0, -2);
		$sender->sendMessage($output);
		return true;
	}
}
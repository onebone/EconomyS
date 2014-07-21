<?php

namespace onebone\economyproperty;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;

class PropertyCommand extends Command implements PluginIdentifiableCommand{
	private $plugin;
	private $command, $pos1, $pos2, $make;

	private $pos;

	public function __construct(EconomyProperty $plugin, $command = "property", $pos1 = "pos1", $pos2 = "pos2", $make = "make"){
		parent::__construct($command, $plugin);
		$this->setUsage("/$command <$pos1|$pos2|$make> [price]");
		$this->setPermission("economyproperty.command.property");
		$this->plugin = $plugin;
		$this->command = $command;
		$this->pos1 = $pos1;
		$this->pos2 = $pos2;
		$this->make = $make;

		$this->pos = array();
	}

	public function getPlugin(){
		return $this->plugin;
	}

	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->plugin->isEnabled() or !$this->testPermission($sender)){
			return false;
		}

		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game.");
			return true;
		}

		switch(array_shift($params)){
			case $this->pos1:
				if(!$sender->hasPermission("economyproperty.command.property.pos1")){
					return false;
				}
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				$this->pos[$sender->getName()][0] = array(
					(int)$sender->getX(),
					(int)$sender->getZ(),
					$sender->getLevel()->getName()
				);
				$sender->sendMessage("[EconomyProperty] First position has saved.");
				break;
			case $this->pos2:
				if(!$sender->hasPermission("economyproperty.command.property.pos2")){
					return false;
				}
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				if(!isset($this->pos[$sender->getName()][0])){
					$sender->sendMessage("[EconomyProperty] Please set your first position.");
					break;
				}
				$this->pos[$sender->getName()][1] = array(
					(int)$sender->getX(),
					(int)$sender->getZ(),
				);
				$sender->sendMessage("[EconomyProperty] Second position has saved.");
				break;
			case $this->make:
				if(!$sender->hasPermission("economyproperty.command.property.make")){
					return false;
				}
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				$price = array_shift($params);
				if(!is_numeric($price) and (isset($params[0]) and !is_numeric($params[0]))){
					$sender->sendMessage("Usage: /{$this->command} {$this->make} <price> [rent time]");
					break;
				}
				if(!isset($this->pos[$sender->getName()][1])){
					$sender->sendMessage("Please check if your positions are all set.");
					break;
				}
				$level = Server::getInstance()->getLevelByName($this->pos[$sender->getName()][0][2]);
				if(!$level instanceof Level){
					$sender->sendMessage("The property area where you are trying to make is corrupted.");
					break;
				}
				$first = array(
					$this->pos[$sender->getName()][0][0],
					$this->pos[$sender->getName()][0][1]
				);
				$end = array(
					$this->pos[$sender->getName()][1][0],
					$this->pos[$sender->getName()][1][1]
				);
				$result = $this->plugin->registerArea($first, $end, $level, $price, $sender->getY(), isset($params[0]) ? $params[0] : null);
				if($result){
					$sender->sendMessage("[EconomyProperty] Property has successfully created.");
				}else{
					$sender->sendMessage("[EconomyProperty] You are trying to overlap with other property area.");
				}
				break;
			default:
				$sender->sendMessage("Usage: ".$this->usageMessage);
		}
		return true;
	}
}
<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyproperty;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

class PropertyCommand extends Command implements PluginIdentifiableCommand{
	private $plugin;
	private $command, $pos1, $pos2, $make, $touchPos;

	private $pos;

	public function __construct(EconomyProperty $plugin, $command = "property", $pos1 = "pos1", $pos2 = "pos2", $make = "make", $touchPos = "touchpos"){
		parent::__construct($command);
		$this->setUsage("/$command <$pos1|$pos2|$make> [price]");
		$this->setPermission("economyproperty.command.property;economyproperty.command.property.pos1;economyproperty.command.property.pos2;");
		$this->setDescription("Property manage command");
		$this->plugin = $plugin;
		$this->command = $command;
		$this->pos1 = $pos1;
		$this->pos2 = $pos2;
		$this->make = $make;
		$this->touchPos = $touchPos;
		$this->pos = array();
	}

	public function getPlugin(): Plugin{
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $label, array $params): bool{
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
					$sender->sendMessage("[EconomyProperty] You don't have permission to use this command.");
					return false;
				}
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				$this->pos[$sender->getName()][0] = array(
					(int)$sender->getX(),
					(int)$sender->getZ(),
					$sender->getLevel()->getFolderName()
				);
				$sender->sendMessage("[EconomyProperty] First position has been saved.");
				break;
			case $this->pos2:
				if(!$sender->hasPermission("economyproperty.command.property.pos2")){
					$sender->sendMessage("[EconomyProperty] You don't have permission to use this command.");
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
				$sender->sendMessage("[EconomyProperty] Second position has been saved.");
				break;
			case $this->make:
				if(!$sender->hasPermission("economyproperty.command.property.make")){
					$sender->sendMessage("[EconomyProperty] You don't have permission to use this command.");
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
				$result = $this->plugin->registerArea($first, $end, $level, $price, $sender->getY(), (isset($params[0]) ? $params[0] : null), $sender->getYaw());
				if($result){
					$sender->sendMessage("[EconomyProperty] Property has successfully created.");
				}else{
					$sender->sendMessage("[EconomyProperty] You are trying to overlap with other property area.");
				}
				break;
			case $this->touchPos:
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				$result = $this->plugin->switchTouchQueue($sender->getName());
				$sender->sendMessage($result ? "[EconomyProperty] Your touch queue has been turned on.":"[EconomyProperty] Your touch queue has been turned off.");
				break;
			case "rm":
				$id = array_shift($params);

				if(!is_numeric($id)){
					$sender->sendMessage(TextFormat::RED . "Usage: " . $command->getUsage());
					return true;
				}

				if($this->plugin->propertyExists($id)){
					$this->plugin->removeProperty($id);
					$sender->sendMessage("[EconomyProperty] Removed property #".$id);
				}else{
					$sender->sendMessage("[EconomyProperty] There is no property with id #".$id);
				}
				return true;
			default:
				$sender->sendMessage("Usage: ".$this->usageMessage);
		}
		return true;
	}

	public function mergePosition($player, $index, $data){
		$this->pos[$player][$index] = $data;
	}
}

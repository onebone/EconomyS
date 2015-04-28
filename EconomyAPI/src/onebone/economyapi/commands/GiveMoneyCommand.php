<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2015  onebone <jyc00410@gmail.com>
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

namespace onebone\economyapi\commands;

use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class GiveMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "givemoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <amount>");
		$this->setDescription("Gives money to player");
		$this->setPermission("economyapi.command.givemoney");
	}
	
	public function exec(CommandSender $sender, array $args){
		$player = array_shift($args);
		$amount = array_shift($args);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: /".$this->getName()." <player> <amount>");
			return true;
		}
		
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("givemoney-invalid-number", $sender->getName()));
			return true;
		}
		
		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		$result = $this->getPlugin()->addMoney($player, $amount);
		$output = "";
		switch($result){
			case -2: // CANCELLED
			$output .= "Your request have been cancelled";
			break;
			case -1: // NOT_FOUND
			$output .= $this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4"));
			break;
			// INVALID is already checked
			case 1: // SUCCESS
			$output .= $this->getPlugin()->getMessage("givemoney-gave-money", $sender->getName(), array($amount, $player, "%3", "%4"));
			if($p instanceof Player){
				$p->sendMessage($this->getPlugin()->getMessage("givemoney-money-given", $sender->getName(), array($amount, "%2", "%3", "%4")));
			}
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
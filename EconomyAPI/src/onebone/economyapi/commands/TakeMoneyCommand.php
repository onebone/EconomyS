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

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class TakeMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "takemoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.takemoney");
		$this->setDescription("Take others' money");
	}
	
	public function exec(CommandSender $sender, array $params){
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: /".$this->getName()." <player> <amount>");
			return true;
		}
		
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("takemoney-invalid-number", $sender->getName()));
			return true;
		}
		
		/*$p = $this->getPlugin()->getServer()->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}else{
			$result = $this->getPlugin()->accountExists($player);
			if($result === false){
				$sender->sendMessage($this->getPlugin()->getMessage("player-never-connected", $sender->getName(), array($player, "%2", "%3", "%4")));
				return true;
			}
		}*/

		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //
		
		$result = $this->getPlugin()->reduceMoney($player, $amount, false, "TakeMoneyCommand");
		$output = "";
		switch($result){
			case EconomyAPI::RET_SUCCESS:
			$output .= $this->getPlugin()->getMessage("takemoney-took-money", $sender->getName(), array($player, $amount, "%3", "%4"));
			break;
			case EconomyAPI::RET_INVALID:
			$output .= $this->getPlugin()->getMessage("takemoney-player-lack-of-money", $sender->getName(), array($player, $amount, $this->getPlugin()->myMoney($player), "%4"));
			break;
			default:
			$output .= $this->getPlugin()->getMessage("takemoney-failed", $sender->getName());
		}
		$sender->sendMessage($output);
		return true;
	}
}
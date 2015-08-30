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

class SetMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "setmoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <money>");
		$this->setDescription("Sets player's money");
		$this->setPermission("economyapi.command.setmoney");
	}

	public function exec(CommandSender $sender, array $args){
		$player = array_shift($args);
		$money = array_shift($args);

		if(trim($player) === "" or trim($money) === ""){
			$sender->sendMessage("Usage: /".$this->getName()." <player> <money>");
			return true;
		}

		//  Player finder  //
		$server = Server::getInstance();
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		// END //

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
			if($p instanceof Player){
				$p->sendMessage($this->getPlugin()->getMessage("setmoney-set", $p->getName(), array($money, $sender->getName(), "%3", "%4")));
			}
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}

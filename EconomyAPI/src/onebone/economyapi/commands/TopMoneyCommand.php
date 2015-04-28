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
use pocketmine\Server;

use onebone\economyapi\EconomyAPI;

class TopMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "topmoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <page>");
		$this->setDescription("Shows top money list");
		$this->setPermission("economyapi.command.topmoney");
	}
	
	public function exec(CommandSender $sender, array $params){
		$page = array_shift($params);
		
		$moneyData = $this->getPlugin()->getAllMoney();
		
		$server = Server::getInstance();
		$banList = $server->getNameBans(); // TODO TopMoney Command
		arsort($moneyData["money"]);
		$n = 1;
		$max = ceil((count($moneyData["money"]) - count($banList->getEntries()) - ($this->getPlugin()->getConfigurationValue("add-op-at-rank") ? 0 : count($server->getOPs()->getAll()))) / 5);
		$page = max(1, $page);
		$page = min($max, $page);
		$page = (int)$page;
		
		$output = "- Showing top money list ($page of $max) -\n";
		$message = ($this->getPlugin()->getMessage("topmoney-format", $sender->getName(), array("%1", "%2", "%3", "%4"))."\n");
		
		foreach($moneyData["money"] as $player => $money){
			if($banList->isBanned($player)) continue;
			if($server->isOp(strtolower($player)) and ($this->getPlugin()->getConfigurationValue("add-op-at-rank") === false)) continue;
			$current = (int)ceil($n / 5);
			if($current === $page){
				$output .= str_replace(array("%1", "%2", "%3"), array($n, $player, $money), $message);
			}elseif($current > $page){
				break;
			}
			++$n;
		}
		$sender->sendMessage($output);
		return true;
	}
}
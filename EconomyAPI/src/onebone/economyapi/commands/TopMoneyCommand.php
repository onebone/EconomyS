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
use onebone\economyapi\task\SortTask;

class TopMoneyCommand extends EconomyAPICommand{
	public function __construct(EconomyAPI $plugin, $cmd = "topmoney"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <page>");
		$this->setDescription("Shows top money list");
		$this->setPermission("economyapi.command.topmoney");
	}
	
	public function exec(CommandSender $sender, array $params){
		$page = array_shift($params);
		
		$ops = $this->getPlugin()->getServer()->getOPs()->getAll();
		$entries = $this->getPlugin()->getServer()->getNameBans()->getEntries();
		
		$banList = [];
		foreach($entries as $entry){
			$banList[$entry->getName()] = true;
		}
		
		$this->getPlugin()->getServer()->getScheduler()->scheduleAsyncTask(new SortTask($sender->getName(), $this->getPlugin()->getAllMoney()["money"], $this->getPlugin()->getConfigurationValue("add-op-at-rank"), $page, $ops, $banList));
		return true;
	}
}

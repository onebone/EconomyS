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

use onebone\economyapi\EconomyAPI;

class MyStatusCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "mystatus"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd");
		$this->setDescription("Shows your money status");
		$this->setPermission("economyapi.command.mystatus");
	}
	
	public function exec(CommandSender $sender, array $params){
		$money = $this->getPlugin()->getAllMoney();
		
		$allMoney = 0;
		foreach($money["money"] as $m){
			$allMoney += $m;
		}
		$topMoney = 0;
		if($allMoney > 0){
			$topMoney = round((($money["money"][strtolower($sender->getName())] / $allMoney) * 100), 2);
		}
		
		$sender->sendMessage($this->getPlugin()->getMessage("mystatus-show", $sender->getName(), array($topMoney, "%2", "%3", "%4")));
		return true;
	}
}

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

namespace onebone\economyapi\task;


use onebone\economyapi\EconomyAPI;
use pocketmine\scheduler\PluginTask;

class MySQLPingTask extends PluginTask{
	private $mysql;

	public function __construct(EconomyAPI $plugin, \mysqli $mysql){
		parent::__construct($plugin);

		$this->mysql = $mysql;
	}

	public function onRun(int $currentTick){
		if(!$this->mysql->ping()){
			$this->getOwner()->openProvider();
		}
	}
}

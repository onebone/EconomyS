<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2021  onebone <me@onebone.me>
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

use mysqli;
use onebone\economyapi\EconomyAPI;

use onebone\economyapi\provider\MySQLProvider;
use pocketmine\scheduler\Task;

class MySQLPingTask extends Task {
	private $plugin;
	private $mysql;

	public function __construct(EconomyAPI $plugin, mysqli $mysql) {
		$this->plugin = $plugin;
		$this->mysql = $mysql;
	}

	public function onRun(): void {
		if(!$this->mysql->ping()) {
			if($this->plugin->getDefaultCurrency()->getProvider() === $this) {
				$this->plugin->getDefaultCurrency()->setProvider(new MySQLProvider($this->plugin));
			}
		}
	}
}

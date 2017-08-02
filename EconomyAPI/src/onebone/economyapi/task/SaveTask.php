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

if(version_compare(\pocketmine\API_VERSION, "3.0.0-ALPHA7") >= 0){
	abstract class _SaveTask extends PluginTask{
		public function onRun(int $currentTick){
			$this->_onRun($currentTick);
		}

		abstract public function _onRun(int $currentTick);
	}
}else{
	abstract class _SaveTask extends PluginTask{
		public function onRun($currentTick){
			$this->_onRun($currentTick);
		}

		abstract public function _onRun(int $currentTick);
	}
}

class SaveTask extends _SaveTask{
	public function __construct(EconomyAPI $plugin){
		parent::__construct($plugin);
	}

	public function _onRun(int $currentTick){
		$this->getOwner()->saveAll();
	}
}


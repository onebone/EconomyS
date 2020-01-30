<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
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

namespace onebone\economyland\land;

use onebone\economyland\EconomyLand;
use onebone\economyland\provider\Provider;
use onebone\economyland\task\LandUnloadTask;

class LandManager {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land[] */
	private $lands = [];
	/** @var Provider */
	private $provider;

	public function __construct(EconomyLand $plugin, Provider $provider) {
		$this->plugin = $plugin;
		$this->provider = $provider;

		$plugin->getScheduler()->scheduleDelayedRepeatingTask(new LandUnloadTask($this),
			$plugin->getPluginConfiguration()->getLandUnloadTaskPeriod(),
			$plugin->getPluginConfiguration()->getLandUnloadTaskPeriod());
	}

	public function getLandAt(int $x, int $z, string $worldName): ?Land {
		foreach($this->lands as $land) {
			if($land->isInside($x, $z, $worldName)) {
				return $land;
			}
		}

		$land = $this->provider->getLandByPosition($x, $z, $worldName);
		if($land !== null) {
			$this->lands[] = $land;
		}

		return $land;
	}

	public function unloadLands() {
		$now = microtime(true);
		$boundary = $this->plugin->getPluginConfiguration()->getLandUnloadAfter();

		$this->lands = array_filter($this->lands, function($val) use ($now, $boundary) {
			/** @var $val Land */
			return $now - $val->getLastAccess() < $boundary;
		});
	}
}

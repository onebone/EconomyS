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
use pocketmine\level\Level;
use pocketmine\math\Vector2;
use pocketmine\Player;

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

	public function createLand(Vector2 $start, Vector2 $end, Level $world, Player $owner, LandOption $option): Land {
		return new Land($this->plugin, $this->provider->getNewId(),
			$start, $end, $world, $owner->getName(), $option);
	}

	public function addLand(Land $land): void {
		$this->provider->addLand($land);
	}

	public function setLand(Land $land): void {
		$this->lands[$land->getId()] = $land;

		$this->provider->setLand($land);
	}

	/**
	 * @param string $id
	 * @return Land[]
	 */
	public function matchLands(string $id): array {
		return $this->provider->getMatches($id);
	}

	/**
	 * @param string $owner
	 * @return Land[]
	 */
	public function getLandsByOwner(string $owner): array {
		return $this->provider->getLandsByOwner($owner);
	}

	public function getLandAt(int $x, int $z, string $worldName): ?Land {
		foreach($this->lands as $land) {
			if($land->isInside($x, $z, $worldName)) {
				return $land;
			}
		}

		$land = $this->provider->getLandByPosition($x, $z, $worldName);
		if($land !== null) {
			$this->lands[$land->getId()] = $land;
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

	public function save() {
		$this->provider->save();
	}

	public function close() {
		$this->provider->close();
	}
}

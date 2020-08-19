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

use InvalidArgumentException;
use onebone\economyland\EconomyLand;
use pocketmine\level\Level;
use pocketmine\math\Vector2;

final class Land {
	/** @var EconomyLand */
	private $plugin;
	/** @var string */
	private $id;
	/** @var Vector2 */
	private $start, $end;
	/** @var string */
	private $worldName;
	/** @var Level */
	private $world = null;
	/** @var string */
	private $owner;
	/** @var LandOption */
	private $option;
	/** @var float */
	private $lastAccess = 0;

	/**
	 * @param EconomyLand $plugin
	 * @param string $id
	 * @param Vector2 $start
	 * @param Vector2 $end
	 * @param string|Level $world
	 * @param string $owner
	 * @param LandOption $option
	 */
	public function __construct(EconomyLand $plugin, string $id, Vector2 $start, Vector2 $end, $world,
	                            string $owner, LandOption $option) {
		$this->plugin = $plugin;
		$this->id = $id;

		$this->start = new Vector2(min($start->x, $end->x), min($start->y, $end->y));
		$this->end = new Vector2(max($start->x, $end->x), max($start->y, $end->y));

		if($world instanceof Level) {
			$this->worldName = $world->getFolderName();
			$this->world = $world;
		}elseif(is_string($world)) {
			$this->worldName = $world;
			$this->world = $plugin->getServer()->getLevelByName($world);
		}else{
			throw new InvalidArgumentException('Invalid $world variable type given to Land constructor');
		}

		$this->owner = strtolower($owner);
		$this->option = $option;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getStart(): Vector2 {
		return $this->start;
	}

	public function getEnd(): Vector2 {
		return $this->end;
	}

	public function getWorldName(): string {
		return $this->worldName;
	}

	/**
	 * Returns the world instance where land reside on.
	 * This method may return null when world is deleted after
	 * land is created.
	 * @return Level|null
	 */
	public function getWorld(): ?Level {
		if($this->world === null) {
			$this->world = $this->plugin->getServer()->getLevelByName($this->worldName);
		}

		return $this->world;
	}

	public function getOwner(): string {
		$this->lastAccess = microtime(true);
		return $this->owner;
	}

	public function setOwner(string $owner) {
		$this->lastAccess = microtime(true);
		$this->owner = strtolower($owner);
	}

	public function getOption(): LandOption {
		$this->lastAccess = microtime(true);
		return clone $this->option;
	}

	public function setOption(LandOption $option) {
		$this->lastAccess = microtime(true);
		$this->option = $option;
	}

	public function isInside(int $x, int $z, string $worldName): bool {
		return $this->start->x <= $x and $x <= $this->end->x
			and $this->start->y <= $z and $z <= $this->end->y
			and $this->worldName === $worldName;
	}

	public function getLastAccess(): float {
		return $this->lastAccess;
	}
}

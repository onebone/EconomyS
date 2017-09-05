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

namespace onebone\economyland\event;

use pocketmine\event\Event;

class LandAddedEvent extends  Event{
	public static $handlerList = null;

	private $id, $startX, $startZ, $endX, $endZ, $level, $price, $player, $expires;

	public function __construct($id, $startX, $startZ, $endX, $endZ, $level, $price, $player, $expires){
		$this->startX = $startX;
		$this->startZ = $startZ;
		$this->endX = $endX;
		$this->endZ = $endZ;
		$this->level = $level;
		$this->price = $price;
		$this->id = $id;
		$this->player = $player;
		$this->expires = $expires;
	}

	public function getId(){
		return $this->id;
	}

	public function getStartX(){
		return $this->startX;
	}

	public function getStartZ(){
		return $this->startZ;
	}

	public function getEndX(){
		return $this->endX;
	}

	public function getEndZ(){
		return $this->endZ;
	}

	public function getLevel(){
		return $this->level;
	}

	public function getPrice(){
		return $this->price;
	}

	public function getExpires(){
		return $this->expires;
	}

	public function getPlayer(){
		return $this->player;
	}
}
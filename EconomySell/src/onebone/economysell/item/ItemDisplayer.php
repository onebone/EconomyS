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

namespace onebone\economysell\item;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\network\mcpe\protocol\AddItemEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\player\Player;
use pocketmine\Server;

class ItemDisplayer {
	/** @var Position */
	private $pos;
	/** @var Item */
	private $item;
	/** @var Position */
	private $linked;

	private $eid;

	public function __construct(Position $pos, Item $item, Position $linked) {
		$this->pos = $pos;
		$this->item = $item;
		$this->linked = $linked;

		$this->eid = Entity::$entityCount++;
	}

	public function spawnToAll(World $level = null) {
		foreach($level instanceof World ? $level->getPlayers() : Server::getInstance()->getOnlinePlayers() as $player) {
			$this->spawnTo($player);
		}
	}

	public function spawnTo(Player $player) {
		$pk = new AddItemActorPacket();
		$pk->entityRuntimeId = $this->eid;
		$pk->item = $this->item;
		$pk->position = $this->pos->add(0.5, 0, 0.5);
		$pk->motion = new Vector3(0, 0, 0);

		$player->dataPacket($pk);
	}

	public function despawnFromAll(World $level = null) {
		foreach($level instanceof World ? $level->getPlayers() : Server::getInstance()->getOnlinePlayers() as $player) {
			$this->despawnFrom($player);
		}
	}

	public function despawnFrom(Player $player) {
		$pk = new RemoveActorPacket();
		$pk->entityUniqueId = $this->eid;
		$player->dataPacket($pk);
	}

	/**
	 * @return Position
	 */
	public function getLinked() {
		return $this->linked;
	}
}

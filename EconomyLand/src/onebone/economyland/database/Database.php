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

namespace onebone\economyland\database;

use pocketmine\Player;

interface Database{
	public function __construct($fileName, $config, $otherName);
	public function getByCoord($x, $z, $level);
	public function getAll();
	public function getLandById($id);
	public function getLandsByOwner($owner);
	public function getLandsByKeyword($keyword);
	public function getInviteeById($id);
	public function addInviteeById($id, $name);
	public function removeInviteeById($id, $name);
	public function addLand($startX, $endX, $startZ, $endZ, $level, $price, $owner, $expires = null,  $invitee = []);
	public function setOwnerById($id, $owner);
	public function removeLandById($id);
	public function canTouch($x, $z, $level, Player $player);
	public function checkOverlap($startX, $endX, $startZ, $endZ, $level);
	public function close();
	public function save();
}

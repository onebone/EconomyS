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

namespace onebone\economyapi\provider;

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

use pocketmine\player\Player;
use onebone\economyapi\util\Promise;

// Provider is currency-related data provider
interface Provider {
	/**
	 * @param Player|string $player
	 * @return bool
	 */
	public function hasAccount($player): bool;

	/**
	 * @param Player|string $player
	 * @param float $defaultMoney
	 * @return bool
	 */
	public function createAccount($player, float $defaultMoney = 1000): bool;

	/**
	 * @param Player|string $player
	 * @return bool
	 */
	public function removeAccount($player): bool;

	/**
	 * @param Player|string $player
	 * @return float|bool
	 */
	public function getMoney($player);

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function setMoney($player, float $amount): bool;

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function addMoney($player, float $amount): bool;

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function reduceMoney($player, float $amount): bool;

	public function getAll(): array;

	public function sortByRange(int $from, ?int $len): Promise;

	public function getName(): string;

	public function save();

	public function close();
}

<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2019  onebone <jyc00410@gmail.com>
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

namespace onebone\economyapi;

use onebone\economyapi\provider\Provider;
use pocketmine\Player;

interface Currency {
	public function getName(): string;

	/**
	 * Returns if the currency is available to player. Availability
	 * may vary on the world where player is in or position or
	 * something else.
	 * @param Player $player
	 * @return bool
	 */
	public function isCurrencyAvailable(Player $player): bool;

	/**
	 * Returns default money which is given to player for the first
	 * time in forever. It may be overridden when server administrator
	 * sets the default value.
	 * @return float
	 */
	public function getDefaultMoney(): float;

	/**
	 * Returns balance of the player.
	 * @param string $username
	 * @return float|null
	 */
	public function getMoney(string $username): ?float;

	/**
	 * Sets balance of player
	 * @param string $username
	 * @param float  $value
	 * @return bool
	 */
	public function setMoney(string $username, float $value): bool;

	/**
	 * Adds balance of player
	 * @param string $username
	 * @param float  $value
	 * @return bool
	 */
	public function addMoney(string $username, float $value): bool;

	/**
	 * Reduces balance of player
	 * @param string $username
	 * @param float  $value
	 * @return bool
	 */
	public function reduceMoney(string $username, float $value): bool;

	/**
	 * Returns the unit of currency
	 * @return string
	 */
	public function getUnit(): string;

	/**
	 * Formats money into string
	 * @param float $money
	 * @return string
	 */
	public function format(float $money): string;

	/**
	 * Stringify money into string
	 * @param float $money
	 * @return string
	 */
	public function stringify(float $money): string;

	public function getProvider(): Provider;

	public function setProvider(Provider $provider);

	public function save();

	public function close();
}

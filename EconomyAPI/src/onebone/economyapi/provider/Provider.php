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

namespace onebone\economyapi\provider;

use onebone\economyapi\EconomyAPI;

interface Provider{
	public function __construct(EconomyAPI $plugin);

	public function open();

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @return bool
	 */
	public function accountExists($player);

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @param float $defaultMoney
	 * @return bool
	 */
	public function createAccount($player, $defaultMoney = 1000);

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @return bool
	 */
	public function removeAccount($player);

	/**
	 * @param string $player
	 * @return float|bool
	 */
	public function getMoney($player);

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function setMoney($player, $amount);

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function addMoney($player, $amount);

	/**
	 * @param \pocketmine\player\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function reduceMoney($player, $amount);

	/**
	 * @return array
	 */
	public function getAll();

	/**
	 * @return string
	 */
	public function getName();

	public function save();
	public function close();
}

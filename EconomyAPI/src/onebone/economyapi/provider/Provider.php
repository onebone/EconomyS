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

use onebone\economyapi\util\Promise;
use onebone\economyapi\util\TransactionAction;
use onebone\economyapi\util\TransactionResult;
use pocketmine\Player;

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
	 * @return int Result code or old balance value, returns {@see EconomyAPI::RET_UNAVAILABLE}
	 *          if the resulting amount is exceeding max balance, {@see EconomyAPI::RET_PROVIDER_FAILURE}
	 *          if operations failed. Returns old balance value(>= 0) if operation had succeed.
	 */
	public function setMoney($player, float $amount): int;

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return int Result code, returns {@see EconomyAPI::RET_UNAVAILABLE} if the resulting
	 *           amount is exceeding max balance, {@see EconomyAPI::RET_PROVIDER_FAILURE}
	 *           if operations failed.
	 */
	public function addMoney($player, float $amount): int;

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return int Result code, returns {@see EconomyAPI::RET_UNAVAILABLE} if the resulting
	 *           amount is negative value, {@see EconomyAPI::RET_PROVIDER_FAILURE} if operations
	 *           have failed.
	 */
	public function reduceMoney($player, float $amount): int;

	public function getAll(): array;

	public function sortByRange(int $from, ?int $len): Promise;

	/**
	 * @param TransactionAction[] $actions
	 * @return TransactionResult
	 */
	public function executeTransaction(array $actions): TransactionResult;

	public function getName(): string;

	public function save();

	public function close();
}

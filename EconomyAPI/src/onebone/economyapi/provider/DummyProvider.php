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

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\util\Promise;
use onebone\economyapi\util\TransactionResult;

class DummyProvider implements Provider {
	public function hasAccount(Player|string $player): bool {
		return false;
	}

	public function createAccount(Player|string $player, float $defaultMoney = 1000): bool {
		return false;
	}

	public function removeAccount(Player|string $player): bool {
		return false;
	}

	public function getMoney(Player|string $player): bool {
		return false;
	}

	public function setMoney(Player|string $player, float $amount): int {
		return EconomyAPI::RET_PROVIDER_FAILURE;
	}

	public function addMoney(Player|string $player, float $amount): int {
		return EconomyAPI::RET_PROVIDER_FAILURE;
	}

	public function reduceMoney(Player|string $player, float $amount): int {
		return EconomyAPI::RET_PROVIDER_FAILURE;
	}

	public function getAll(): array {
		return [];
	}

	public function sortByRange(int $from, ?int $len): Promise {
		$promise = new Promise();
		$promise->reject(null);

		return $promise;
	}

	public function executeTransaction(array $actions): TransactionResult {
		return new TransactionResult(TransactionResult::FAILURE, -9999, []);
	}

	public function getName(): string {
		return "Dummy";
	}

	public function save() {

	}

	public function close() {

	}
}

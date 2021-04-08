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

namespace onebone\economyapi\internal;

use onebone\economyapi\util\TransactionAction;

interface ReversionProvider {
	/**
	 * Pending balance that is not reflected to the database. Returns 0
	 * if none.
	 *
	 * @param string $player
	 * @return float
	 */
	public function getPendingBalance(string $player): float;

	/**
	 * Returns all pending balances
	 *
	 * @return TransactionAction[]
	 */
	public function getAllPending(): array;

	public function clearPending(): void;

	/**
	 * @param array $actions
	 */
	public function addPendingActions(array $actions): void;

	/**
	 * @param TransactionAction[] $actions
	 */
	public function saveRevertActions(array $actions): void;

	public function close(): void;
}

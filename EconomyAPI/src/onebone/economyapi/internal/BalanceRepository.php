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

use onebone\economyapi\currency\Currency;
use onebone\economyapi\EconomyAPI;
use onebone\economyapi\provider\Provider;
use onebone\economyapi\provider\RevertAction;
use onebone\economyapi\util\Promise;
use onebone\economyapi\util\Transaction;
use onebone\economyapi\util\TransactionAction;
use onebone\economyapi\util\TransactionResult;
use pocketmine\Player;

/**
 * @internal
 * Holds reference to various Providers and returns appropriate values for the request
 *
 * THIS IS INTERNAL CLASS, AND IS SUBJECT TO CHANGE ANYTIME WITHOUT NOTICE.
 */
class BalanceRepository {
	/** @var Currency */
	private $currency;
	/** @var Provider */
	private $provider;
	/** @var ReversionProvider */
	private $reversionProvider;

	public function __construct(Currency $currency, Provider $provider, ReversionProvider $reversionProvider) {
		$this->currency = $currency;
		$this->provider = $provider;
		$this->reversionProvider = $reversionProvider;
	}

	public function tryFlushPending() {
		$pending = $this->reversionProvider->getAllPending();
		if($this->provider->executeTransaction(
			$this->revertActionToTransactionAction($pending)
		)) {
			$this->reversionProvider->clearPending();
		}
	}

	/**
	 * @param RevertAction[] $reverts
	 * @return TransactionAction[]
	 */
	private function revertActionToTransactionAction(array $reverts): array {
		$actions = [];
		foreach($reverts as $entry) {
			if($entry->getType() === RevertAction::ADD) {
				$type = Transaction::ACTION_ADD;
			}else{
				$type = Transaction::ACTION_REDUCE;
			}

			$actions[] = new TransactionAction($type, $entry->getPlayer(), $entry->getValue(), $this->currency);
		}

		return $actions;
	}

	public function getAllBalances(): array {
		return $this->provider->getAll();
	}

	/**
	 * @param string|Player $player
	 * @return bool
	 */
	public function hasAccount($player): bool {
		return $this->provider->hasAccount($player);
	}

	/**
	 * @param string|Player $player
	 * @param float $defaultBalance
	 * @return bool
	 */
	public function createAccount($player, float $defaultBalance): bool {
		return $this->provider->createAccount($player, $defaultBalance);
	}

	public function sortByRange(int $from, ?int $len): Promise {
		return $this->provider->sortByRange($from, $len);
	}

	/**
	 * @param string|Player $player
	 * @return bool|float
	 */
	public function getMoney($player) {
		$balance = $this->provider->getMoney($player);
		if($balance === false) return $balance;

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		return $balance + $this->reversionProvider->getPendingBalance($player);
	}

	/**
	 * @param TransactionAction[] $actions
	 * @return TransactionResult
	 */
	public function executeTransaction(array $actions): TransactionResult {
		// TODO merge with pending transaction?
		return $this->provider->executeTransaction($actions);
	}

	/**
	 * @param RevertAction[] $actions
	 */
	public function revert(array $actions) {
		$this->reversionProvider->addRevertActions($actions);
	}

	/**
	 * @param string|Player $player
	 * @param float $value
	 * @param TransactionAction|null $action Action to be used to revert the operation
	 *
	 * @return int Result code
	 * <ul>
	 *      <li>{@see EconomyAPI::RET_SUCCESS}: Operation has succeed</li>
	 *      <li>{@see EconomyAPI::RET_NO_ACCOUNT}: There is no account for the player</li>
	 *      <li>{@see EconomyAPI::RET_UNAVAILABLE}: Resulting amount of balance of the request exceeds the maximum balance</li>
	 *      <li>{@see EconomyAPI::RET_PROVIDER_FAILURE}: Provider attached to the Currency has failed to process the request</li>
	 * </ul>
	 */
	public function addMoney($player, float $value, ?TransactionAction &$action): int {
		// TODO provider should check constraints such as limiting balance going over maximum value by itself

		$result = $this->provider->addMoney($player, $value);
		if($result === EconomyAPI::RET_SUCCESS) {
			$action = new TransactionAction(Transaction::ACTION_REDUCE, $player, $value, $this->currency);
		}else{
			$action = null;
		}

		return $result;
	}

	/**
	 * @param string|Player $player
	 * @param float $value
	 * @param TransactionAction|null $action Action to be used to revert the operation
	 *
	 * @return int Result code
	 * <ul>
	 *      <li>{@see EconomyAPI::RET_SUCCESS}: Operation has succeed</li>
	 *      <li>{@see EconomyAPI::RET_NO_ACCOUNT}: There is no account for the player</li>
	 *      <li>{@see EconomyAPI::RET_UNAVAILABLE}: Resulting amount of balance of the request is negative value</li>
	 *      <li>{@see EconomyAPI::RET_PROVIDER_FAILURE}: Provider attached to the Currency has failed to process the request</li>
	 * </ul>
	 */
	public function reduceMoney($player, float $value, ?TransactionAction &$action): int {
		// TODO provider should check constraints such as limiting balance going to negative value by itself

		$result = $this->provider->reduceMoney($player, $value);
		if($result === EconomyAPI::RET_SUCCESS) {
			$action = new TransactionAction(Transaction::ACTION_ADD, $player, $value, $this->currency);
		}else{
			$action = null;
		}

		return $result;
	}

	/**
	 * @param string|Player $player
	 * @param float $value
	 * @param TransactionAction|null $action Action to be used to revert the operation
	 *
	 * @return int Result code or old balance
	 * <ul>
	 *      <li>{@see EconomyAPI::RET_UNAVAILABLE}: Resulting amount of balance of the request is out of range</li>
	 *      <li>{@see EconomyAPI::RET_NO_ACCOUNT}: There is no account for the player</li>
	 *      <li>{@see EconomyAPI::RET_PROVIDER_FAILURE}: Provider attached to the Currency has failed to process the request</li>
	 *      <li>Other values (>= 0): Operation has succeed, old balance value</li>
	 * </ul>
	 */
	public function setMoney($player, float $value, ?TransactionAction &$action): int {
		// TODO provider should check constraints such as limiting balance going over maximum value or to negative value by itself

		$result = $this->provider->setMoney($player, $value);

		$action = null;
		if($result >= 0) { // operation has succeed and provider returned old balance value
			$delta = $value - $result;
			if($delta < 0) {
				$action = new TransactionAction(Transaction::ACTION_ADD, $player, $delta, $this->currency);
			}else if($delta > 0) {
				$action = new TransactionAction(Transaction::ACTION_REDUCE, $player, $delta, $this->currency);
			}
		}

		return $result;
	}

	public function close() {
		$this->provider->close();
		$this->reversionProvider->close();
	}

	public function save() {
		$this->provider->save();
		$this->reversionProvider->save();
	}
}

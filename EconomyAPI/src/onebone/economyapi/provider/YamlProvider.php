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
use onebone\economyapi\task\YamlSortTask;
use onebone\economyapi\util\Promise;
use onebone\economyapi\util\Transaction;
use onebone\economyapi\util\TransactionAction;
use pocketmine\Player;
use pocketmine\utils\Config;

class YamlProvider implements Provider {
	/**
	 * @var Config
	 */
	private $config;

	/** @var EconomyAPI */
	private $plugin;

	private $money;

	public function __construct(EconomyAPI $plugin, string $fileName) {
		$this->plugin = $plugin;

		$this->config = new Config($this->plugin->getDataFolder() . $fileName, Config::YAML, [
			"version" => 2,
			"money"   => []
		]);
		$this->money = $this->config->getAll();
	}

	public function hasAccount($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		return isset($this->money["money"][$player]);
	}

	public function createAccount($player, float $defaultMoney = 1000): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(!isset($this->money["money"][$player])) {
			$this->money["money"][$player] = $defaultMoney;
			return true;
		}
		return false;
	}

	public function removeAccount($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->money["money"][$player])) {
			unset($this->money["money"][$player]);
			return true;
		}
		return false;
	}

	public function getMoney($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->money["money"][$player])) {
			return $this->money["money"][$player];
		}
		return false;
	}

	public function setMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->money["money"][$player])) {
			$this->money["money"][$player] = $amount;
			return true;
		}
		return false;
	}

	public function addMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->money["money"][$player])) {
			$this->money["money"][$player] += $amount;
			return true;
		}
		return false;
	}

	public function reduceMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->money["money"][$player])) {
			$this->money["money"][$player] -= $amount;
			return true;
		}
		return false;
	}

	public function getAll(): array {
		return isset($this->money["money"]) ? $this->money["money"] : [];
	}

	public function sortByRange(int $from, ?int $len): Promise {
		$promise = new Promise();
		$task = new YamlSortTask($promise, $this->money['money'], $from, $len);

		$this->plugin->getServer()->getAsyncPool()->submitTask($task);

		return $promise;
	}

	/**
	 * @param TransactionAction[] $actions
	 * @return bool
	 */
	public function executeTransaction(array $actions): bool {
		if(!$this->validateTransaction($actions)) return false;

		foreach($actions as $action) {
			$player = strtolower($action->getPlayer());
			$amount = $action->getAmount();
			$type = $action->getType();

			switch($type) {
				case Transaction::ACTION_SET:
					$this->money['money'][$player] = $amount;
					break;
				case Transaction::ACTION_ADD:
					$this->money['money'][$player] += $amount;
					break;
				case Transaction::ACTION_REDUCE:
					$this->money['money'][$player] -= $amount;
					break;
			}
		}

		return true;
	}

	/**
	 * @param TransactionAction[] $actions
	 * @return bool
	 */
	private function validateTransaction(array $actions): bool {
		foreach($actions as $action) {
			$player = strtolower($action->getPlayer());
			$amount = $action->getAmount();
			$type = $action->getType();

			switch($type) {
				case Transaction::ACTION_SET:
					if($amount < 0) return false;
					break;
				case Transaction::ACTION_REDUCE:
					if(!isset($this->money['money'][$player])) return false;

					$money = $this->money['money'][$player];
					if($money - $amount < 0) return false;
					break;
				case Transaction::ACTION_ADD:
					if(!isset($this->money['money'][$player])) return false;
					break;
			}
		}

		return true;
	}

	public function getName(): string {
		return "Yaml";
	}

	public function close() {
		$this->save();
	}

	public function save() {
		$this->config->setAll($this->money);
		$this->config->save();
	}
}

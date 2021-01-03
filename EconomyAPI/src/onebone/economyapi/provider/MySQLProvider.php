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

use mysqli;
use onebone\economyapi\EconomyAPI;
use onebone\economyapi\task\MySQLPingTask;
use pocketmine\player\Player;
use onebone\economyapi\util\Promise;

class MySQLProvider implements Provider {
	/**
	 * @var mysqli
	 */
	private $db;

	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;

		$config = $this->plugin->getPluginConfig()->getProviderSettings();

		$this->db = new mysqli($config["host"] ?? "127.0.0.1", $config["user"] ?? "onebone", $config["password"] ?? "hello_world", $config["db"] ?? "economyapi", $config["port"] ?? 3306);
		if($this->db->connect_error) {
			$this->plugin->getLogger()->critical("Could not connect to MySQL server: " . $this->db->connect_error);
			return;
		}
		if(!$this->db->query("CREATE TABLE IF NOT EXISTS user_money(
			username VARCHAR(20) PRIMARY KEY,
			money FLOAT
		);")) {
			$this->plugin->getLogger()->critical("Error creating table: " . $this->db->error);
			return;
		}

		$this->plugin->getScheduler()->scheduleRepeatingTask(new MySQLPingTask($this->plugin, $this->db), 600);
	}

	/**
	 * @param Player|string $player
	 * @param float $defaultMoney
	 * @return bool
	 */
	public function createAccount($player, float $defaultMoney = 1000.0): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(!$this->hasAccount($player)) {
			$this->db->query("INSERT INTO user_money (username, money) VALUES ('" . $this->db->real_escape_string($player) . "', $defaultMoney);");
			return true;
		}
		return false;
	}

	/**
	 * @param Player|string $player
	 * @return bool
	 */
	public function hasAccount($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$result = $this->db->query("SELECT * FROM user_money WHERE username='" . $this->db->real_escape_string($player) . "'");
		return $result->num_rows > 0 ? true : false;
	}

	/**
	 * @param Player|string $player
	 * @return bool
	 */
	public function removeAccount($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($this->db->query("DELETE FROM user_money WHERE username='" . $this->db->real_escape_string($player) . "'") === true) return true;
		return false;
	}

	/**
	 * @param string $player
	 * @return float|bool
	 */
	public function getMoney($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$res = $this->db->query("SELECT money FROM user_money WHERE username='" . $this->db->real_escape_string($player) . "'");
		$ret = $res->fetch_array()[0] ?? false;
		$res->free();
		return $ret;
	}

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function setMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$amount = (float) $amount;

		return $this->db->query("UPDATE user_money SET money = $amount WHERE username='" . $this->db->real_escape_string($player) . "'");
	}

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function addMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$amount = (float) $amount;

		return $this->db->query("UPDATE user_money SET money = money + $amount WHERE username='" . $this->db->real_escape_string($player) . "'");
	}

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function reduceMoney($player, float $amount): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$amount = (float) $amount;

		return $this->db->query("UPDATE user_money SET money = money - $amount WHERE username='" . $this->db->real_escape_string($player) . "'");
	}

	/**
	 * @return array
	 */
	public function getAll(): array {
		$res = $this->db->query("SELECT * FROM user_money");

		$ret = [];
		foreach($res->fetch_all() as $val) {
			$ret[$val[0]] = $val[1];
		}

		$res->free();

		return $ret;
	}

	public function sortByRange(int $from, ?int $len): Promise {
		// TODO implement here
		$promise = new Promise();
		$promise->reject(null);

		return $promise;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return "MySQL";
	}

	public function save() {
	}

	public function close() {
		if($this->db instanceof mysqli) {
			$this->db->close();
		}
	}
}

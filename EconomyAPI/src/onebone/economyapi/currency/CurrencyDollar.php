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

namespace onebone\economyapi\currency;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\provider\Provider;
use onebone\economyapi\provider\YamlProvider;
use pocketmine\Player;

class CurrencyDollar implements Currency {
	/** @var Provider $provider */
	private $provider;

	public function __construct(EconomyAPI $plugin) {
		// TODO make this customizable
		$this->provider = new YamlProvider($plugin, 'Money.yml');
	}

	public function getName(): string {
		return 'Dollar';
	}

	public function isCurrencyAvailable(Player $player): bool {
		return true;
	}

	public function getDefaultMoney(): float {
		return 1000;
	}

	public function getMoney(string $username): ?float {
		return $this->provider->getMoney($username);
	}

	public function setMoney(string $username, float $value): bool {
		return $this->provider->setMoney($username, $value);
	}

	public function addMoney(string $username, float $value): bool {
		return $this->provider->addMoney($username, $value);
	}

	public function reduceMoney(string $username, float $value): bool {
		return $this->provider->reduceMoney($username, $value);
	}

	public function getSymbol(): string {
		return '$';
	}

	public function format(float $money): string {
		return sprintf('$%.2f', $money);
	}

	public function stringify(float $money): string {
		$digits = floor($money);
		$decimal = floor(($money - $digits) * 100);

		return $digits . (' dollar' . ($digits > 1 ? 's' : '')) . $decimal . (' cent' . ($decimal > 1 ? 's' : ''));
	}

	public function getProvider(): Provider {
		return $this->provider;
	}

	public function setProvider(Provider $provider) {
		$this->provider = $provider;
	}

	public function save() {
		$this->provider->save();
	}

	public function close() {
		$this->provider->close();
	}
}

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

namespace onebone\economyapi\event\account;

use onebone\economyapi\currency\Currency;
use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\event\Issuer;

class CreateAccountEvent extends EconomyAPIEvent {
	private $username, $defaultMoney, $currency;

	public function __construct(EconomyAPI $plugin, $username, Currency $currency, $defaultMoney, ?Issuer $issuer) {
		parent::__construct($plugin, $issuer);
		$this->username = $username;
		$this->currency = $currency;
		$this->defaultMoney = $defaultMoney;
	}

	public function getUsername() {
		return $this->username;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function setDefaultMoney($money) {
		$this->defaultMoney = $money;
	}

	public function getDefaultMoney() {
		return $this->defaultMoney;
	}
}

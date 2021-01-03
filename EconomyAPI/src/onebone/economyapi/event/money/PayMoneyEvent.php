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

namespace onebone\economyapi\event\money;

use onebone\economyapi\currency\Currency;
use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\event\Issuer;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

class PayMoneyEvent extends EconomyAPIEvent implements Cancellable {
	use CancellableTrait;

	private $payer, $target, $currency, $amount;

	public function __construct(EconomyAPI $plugin, $payer, $target, Currency $currency, float $amount, ?Issuer $issuer) {
		parent::__construct($plugin, $issuer);

		$this->payer = $payer;
		$this->target = $target;
		$this->currency = $currency;
		$this->amount = $amount;
	}

	public function getPayer() {
		return $this->payer;
	}

	public function getTarget() {
		return $this->target;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function getAmount() {
		return $this->amount;
	}
}

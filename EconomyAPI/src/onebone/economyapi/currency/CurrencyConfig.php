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

namespace onebone\economyapi\currency;

class CurrencyConfig {
	/** @var Currency */
	private $currency;
	private $max;
	private $default;
	private $exchange;
	private $isExposed;

	public function __construct(Currency $currency, float $max, ?float $default, array $exchange, bool $isExposed) {
		$this->currency = $currency;

		$this->max = $max;
		$this->default = $default;
		$this->exchange = $exchange;
		$this->isExposed = $isExposed;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function hasMaxMoney(): bool {
		return $this->max >= 0;
	}

	public function getMaxMoney(): float {
		return $this->max;
	}

	public function getDefaultMoney(): ?float {
		return $this->default;
	}

	public function getExchange(): array {
		return $this->exchange;
	}

	public function isExposed(): bool {
		return $this->isExposed;
	}
}

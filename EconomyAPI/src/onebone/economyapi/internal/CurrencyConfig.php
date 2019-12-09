<?php

namespace onebone\economyapi\internal;

use onebone\economyapi\currency\Currency;

class CurrencyConfig {
	/** @var Currency */
	private $currency;
	private $max;
	private $default;
	private $exchange;

	public function __construct(Currency $currency, float $max, float $default, array $exchange) {
		$this->currency = $currency;

		$this->max = $max;
		$this->default = $default;
		$this->exchange = $exchange;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function getMaxMoney(): float {
		return $this->max;
	}

	public function getDefaultMoney(): float {
		return $this->default;
	}

	public function getExchange(): array {
		return $this->exchange;
	}
}

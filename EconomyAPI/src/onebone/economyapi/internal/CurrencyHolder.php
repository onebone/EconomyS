<?php

namespace onebone\economyapi\internal;

use onebone\economyapi\currency\Currency;
use onebone\economyapi\currency\CurrencyConfig;
use onebone\economyapi\provider\Provider;

class CurrencyHolder {
	/** @var Currency */
	private $currency;
	/** @var Provider */
	private $provider;
	/** @var CurrencyConfig */
	private $config = null;

	public function __construct(Currency $currency, Provider $provider) {
		$this->currency = $currency;
		$this->provider = $provider;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function getProvider(): Provider {
		return $this->provider;
	}

	public function setConfig(CurrencyConfig $config) {
		$this->config = $config;
	}

	public function getConfig(): ?CurrencyConfig {
		return $this->config;
	}
}

<?php

namespace onebone\economyapi\config;

use pocketmine\utils\Config;

final class PluginConfig {
	/** @var Config $config */
	private $config;

	public function __construct(Config $config) {
		$this->config = $config;
	}

	public function getProvider(): string {
		return $this->config->get('provider', 'yaml');
	}

	public function getProviderSettings(): array {
		return $this->config->get('provider-settings', []);
	}

	public function getCurrencies(): array {
		return $this->config->get('currencies', []);
	}

	public function getDefaultCurrency(): string {
		return $this->config->get('default-currency', 'dollar');
	}

	public function getAddOpAtRank(): bool {
		return $this->config->get('add-op-at-rank', false);
	}

	public function getAllowPayOffline(): bool {
		return $this->config->get('allow-pay-offline', true);
	}

	public function getDefaultLanguage(): string {
		return $this->config->get('default-lang', 'def');
	}

	public function getAutoSaveInterval(): int {
		return $this->config->get('auto-save-interval', 10);
	}

	public function getCheckUpdate(): bool {
		return $this->config->get('check-update', true);
	}

	public function getUpdateHost(): string {
		return $this->config->get('update-host', 'onebone.me/plugins/economys/api');
	}

	public function getMaxMoney() {
		return $this->config->get("max-money", );
	}

	public function getDefaultMoney() {
		return $this->config->get("default-money");
	}
}

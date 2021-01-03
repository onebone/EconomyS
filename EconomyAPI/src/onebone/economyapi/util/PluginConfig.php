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

namespace onebone\economyapi\util;

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

	public function getSendCommandUsages(): bool {
		return $this->config->get('send-command-usages', true);
	}

	public function getCheckUpdate(): bool {
		return $this->config->get('check-update', true);
	}

	public function getUpdateHost(): string {
		return $this->config->get('update-host', 'onebone.me/plugins/economys/api');
	}
}

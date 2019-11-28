<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2019  onebone <jyc00410@gmail.com>
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

class YamlUserProvider implements UserProvider {
	/** @var $api EconomyAPI */
	private $api;

	public function __construct(EconomyAPI $api) {
		$this->api = $api;
	}

	public function addWallet(string $username, string $wallet): bool {

	}

	public function removeWallet(string $username, string $wallet): bool {
		// TODO: Implement removeWallet() method.
	}

	public function getWallets(string $username): array {
		// TODO: Implement getWallets() method.
	}

	public function setLanguage(string $username, string $lang): bool {
		// TODO: Implement setLanguage() method.
	}

	public function getLanguage(string $username): bool {
		// TODO: Implement getLanguage() method.
	}

	public function save() {
		// TODO: Implement save() method.
	}

	public function close() {
		// TODO: Implement close() method.
	}
}

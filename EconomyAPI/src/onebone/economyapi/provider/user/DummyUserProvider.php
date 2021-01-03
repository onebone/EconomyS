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

namespace onebone\economyapi\provider\user;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\UserInfo;

class DummyUserProvider implements UserProvider {
	public function init() {

	}

	public function getName(): string {
		return 'Dummy';
	}

	public function exists(string $username): bool {
		return false;
	}

	public function setLanguage(string $username, string $lang): bool {
		return false;
	}

	public function getLanguage(string $username): string {
		return '';
	}

	public function getPreferredCurrency(string $username): string {
		return '';
	}

	public function setPreferredCurrency(string $username, string $currency): bool {
		return false;
	}

	public function save() {
	}

	public function close() {
	}

	public function create(string $username): bool {
		return false;
	}

	public function delete(string $username): bool {
		return false;
	}

	public function getUserInfo(string $username): UserInfo {
		$plugin = EconomyAPI::getInstance();
		return new UserInfo($username, $plugin->getPluginConfig()->getDefaultLanguage(), $plugin->getDefaultCurrency());
	}
}

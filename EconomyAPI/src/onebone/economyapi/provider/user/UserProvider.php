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

// UserProvider is data provider to manage user-related data.
// It is instantiated once on EconomyAPI main class unlike
// Provider that is created by each currencies.
use onebone\economyapi\UserInfo;

interface UserProvider {
	public function init();

	public function getName(): string;

	public function create(string $username): bool;

	public function delete(string $username): bool;

	public function exists(string $username): bool;

	public function setLanguage(string $username, string $lang): bool;

	public function getLanguage(string $username): string;

	public function setPreferredCurrency(string $username, string $currency): bool;

	public function getPreferredCurrency(string $username): string;

	public function getUserInfo(string $username): UserInfo;

	public function save();

	public function close();
}

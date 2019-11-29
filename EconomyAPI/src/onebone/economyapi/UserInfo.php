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

namespace onebone\economyapi;

// UserInfo is a data class for delivering user information
// of player
class UserInfo {
	/** @var string $username Username of player */
	public $username;
	/** @var string[] $wallet Type of currencies which player is possessing */
	public $wallet;
	/** @var string $language Language which is set to player */
	public $language;

	public function __construct(string $username, array $wallet, string $language) {
		$this->username = $username;
		$this->wallet = $wallet;
		$this->language = $language;
	}
}

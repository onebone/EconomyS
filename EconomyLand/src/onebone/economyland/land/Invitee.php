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

namespace onebone\economyland\land;

class Invitee {
	private $username;
	/** @var bool */
	private $allowPickup;
	/** @var bool */
	private $allowTouch;

	public function __construct(string $username, bool $allowTouch, bool $allowPickup) {
		$this->username = strtolower($username);
		$this->allowTouch = $allowTouch;
		$this->allowPickup = $allowPickup;
	}

	public function getName(): string {
		return $this->username;
	}

	public function getAllowTouch(): bool {
		return $this->allowTouch;
	}

	public function setAllowTouch(bool $val) {
		$this->allowTouch = $val;
	}

	public function getAllowPickup(): bool {
		return $this->allowPickup;
	}

	public function setAllowPickup(bool $val) {
		$this->allowPickup = $val;
	}
}

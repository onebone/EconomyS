<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
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

namespace onebone\economyland\provider;

use onebone\economyland\land\Land;

class DummyProvider implements Provider {
	public function getNewId(): string {
		return '';
	}

	public function addLand(Land $land): void {

	}

	public function getLand(string $id): ?Land {
		return null;
	}

	public function hasLand(string $id): bool {
		return false;
	}

	public function setLand(Land $land): void {

	}

	public function getLandByPosition(int $x, int $z, string $worldName): ?Land {
		return null;
	}

	public function save(): void {

	}

	public function close(): void {

	}
}

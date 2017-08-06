<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
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


namespace onebone\economysell\provider;


interface DataProvider{
	/**
	 * @param string $file
	 * @param bool $save
	 */
	public function __construct($file, $save);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param array|int $y
	 * @param int $z
	 * @param \pocketmine\level\Level|string $level
	 * @param array $data
	 *
	 * @return bool
	 */
	public function addSell($x, $y = 0, $z = 0, $level = null, $data = []);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param int $y
	 * @param int $z
	 * @param \pocketmine\level\Level|string $level
	 *
	 * @return mixed
	 */
	public function getSell($x, $y = 0, $z = 0, $level = null);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param int $y
	 * @param int $z
	 * @param \pocketmine\level\Level|string $level
	 *
	 * @return bool
	 */
	public function removeSell($x, $y = 0, $z = 0, $level = null);

	/**
	 * @return array
	 */
	public function getAll();

	/**
	 * @return string
	 */
	public function getProviderName();

	public function save();
	public function close();
}

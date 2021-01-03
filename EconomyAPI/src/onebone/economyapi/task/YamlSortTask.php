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

namespace onebone\economyapi\task;

use onebone\economyapi\util\Promise;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class YamlSortTask extends AsyncTask {
	/** @var int */
	private $from, $len;
	private $money;

	public function __construct(Promise $promise, array $money, int $from, ?int $len) {
		$this->storeLocal('promise', $promise);

		$this->money = $money;
		$this->from = $from;
		$this->len = $len;
	}

	public function onRun(): void {
		$money = (array) $this->money;
		arsort($money);

		if($this->from < 0)
			$this->from = 0;

		$this->setResult(array_slice($money, $this->from, $this->len, true));
	}

	public function onCompletion(): void {
		/** @var Promise $promise */
		$promise = $this->fetchLocal('promise');

		if(!$this->isCrashed()) {
			$promise->resolve($this->getResult());
		}else{
			$promise->reject(null);
		}
	}
}

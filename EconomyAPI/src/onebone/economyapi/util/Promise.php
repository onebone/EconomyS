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

class Promise {
	const STATE_PENDING = 0;
	const STATE_FULFILLED = 1;
	const STATE_REJECTED = 2;

	private $state = self::STATE_PENDING;

	private $fulfill = [];
	private $reject = [];

	private $result = null;

	public function then(callable $onFulfill): self {
		if($this->state === self::STATE_FULFILLED) {
			$onFulfill($this->result);
			return $this;
		}

		$this->fulfill[] = $onFulfill;

		return $this;
	}

	public function catch(callable $onFailure): self {
		if($this->state === self::STATE_REJECTED) {
		    $onFailure($this->result);
		    return $this;
		}

		$this->reject[] = $onFailure;

		return $this;
	}

	public function resolve($value) {
		$this->settle(self::STATE_FULFILLED, $value);
	}

	public function reject($value) {
		$this->settle(self::STATE_REJECTED, $value);
	}

	private function settle($state, $value) {
		if($this->state !== self::STATE_PENDING) {
			if($this->state !== $state) {
				throw new \InvalidStateException();
			}

			if($value === $this->result) return;
		}

		$handlers = $state === self::STATE_FULFILLED ? $this->fulfill : $this->reject;

		$this->fulfill = $this->reject = [];

		$this->result = $value;
		$arg = $value;
		foreach($handlers as $handler) {
			$arg = $handler($arg);
		}
	}
}

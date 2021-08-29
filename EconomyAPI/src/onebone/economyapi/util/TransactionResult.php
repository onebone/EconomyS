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

use InvalidArgumentException;
use onebone\economyapi\EconomyAPI;
use onebone\economyapi\provider\RevertAction;

class TransactionResult {
	public const SUCCESS = 0;
	public const FAILURE = 1;

	/** @var int */
	private $state;
	/** @var int */
	private $reason;
	/** @var RevertAction[] */
	private $revertActions = [];

	public function __construct(int $state, int $reason, array $revertActions) {
		if($state < 0 or 1 < $state) throw new InvalidArgumentException('$state should be in the range of [0..1]');

		$this->state = $state;
		$this->reason = $reason;
		$this->revertActions = $revertActions;
	}

	public function getState(): int {
		return $this->state;
	}

	/**
	 * Only used for failed transactions. A successful transaction may have any value.
	 *
	 * See:
	 *      {@see EconomyAPI::RET_UNAVAILABLE}
	 *      {@see EconomyAPI::RET_NO_ACCOUNT}
	 *      {@see EconomyAPI::RET_PROVIDER_FAILURE}
	 *
	 * @return int
	 */
	public function getReason(): int {
		return $this->reason;
	}

	/**
	 * @return RevertAction[]
	 */
	public function getRevertActions(): array {
		return $this->revertActions;
	}
}

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

namespace onebone\economyapi\internal;

use onebone\economyapi\currency\Currency;
use onebone\economyapi\currency\CurrencyConfig;
use onebone\economyapi\provider\Provider;
use onebone\economyapi\task\FlushRevertActionsTask;
use pocketmine\scheduler\TaskScheduler;

// All class in `internal` namespace must be used only
// for internal purposes of EconomyAPI, thus it is subject
// to change any time without any notice. Do not use this
// class anyway.
/** @internal */
final class CurrencyHolder {
	private BalanceRepository $repository;
	private ?CurrencyConfig $config = null;
	private FlushRevertActionsTask $flushTask;

	public function __construct(
		TaskScheduler $scheduler,
		private string $id,
		private Currency $currency,
		Provider $provider
	) {
		$this->repository = new BalanceRepository($currency, $provider, new ReversionProviderImpl());

		$this->flushTask = new FlushRevertActionsTask($this->repository);
		$scheduler->scheduleRepeatingTask($this->flushTask, 20 * 60);
	}

	public function getId(): string {
		return $this->id;
	}

	public function getCurrency(): Currency {
		return $this->currency;
	}

	public function getBalanceRepository(): BalanceRepository {
		return $this->repository;
	}

	public function setConfig(CurrencyConfig $config) {
		$this->config = $config;
	}

	public function getConfig(): ?CurrencyConfig {
		return $this->config;
	}

	public function save() {
		$this->repository->save();
	}

	public function close() {
		$this->flushTask?->getHandler()?->cancel();

		$this->repository->close();
	}
}

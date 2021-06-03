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

use onebone\economyapi\provider\RevertAction;

/**
 * @internal
 *
 * THIS IS INTERNAL CLASS, AND IS SUBJECT TO CHANGE ANYTIME WITHOUT NOTICE.
 */
class ReversionProviderImpl implements ReversionProvider {
	/** @var RevertAction[] */
	private $pending = [];

	public function __construct() {
		// TODO load pending from files
	}

	public function getPendingBalance(string $player): float {
		$player = strtolower($player);

		if(isset($this->pending[$player])) {
			$action = $this->pending[$player];
			$value = $action->getValue();
			if($action->getType() === RevertAction::ADD) {
				return $value;
			}else{
				return -$value;
			}
		}

		return 0;
	}

	public function getAllPending(): array {
		return array_values($this->pending);
	}

	public function clearPending(): void {
		$this->pending = [];
	}

	/**
	 * @param RevertAction[] $actions
	 */
	public function addRevertActions(array $actions): void {
		foreach($actions as $action) {
			$player = strtolower($action->getPlayer());

			$delta = $this->getAbsoluteValue($action);
			if(isset($this->pending[$player])) {
				$delta += $this->getAbsoluteValue($this->pending[$player]);
			}

			if($delta < 0) {
				$this->pending[$player] = new RevertAction(RevertAction::REDUCE, $player, -$delta);
			}else if($delta > 0) {
				$this->pending[$player] = new RevertAction(RevertAction::ADD, $player, -$delta);
			}else{
				unset($this->pending[$player]);
			}
		}
	}

	private function getAbsoluteValue(RevertAction $action): float {
		$value = $action->getValue();

		if($action->getType() === RevertAction::ADD) return $value;
		else return -$value;
	}

	public function save(): void {

	}

	public function close(): void {
		$this->save();
	}
}

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

namespace onebone\economyland\land;

use pocketmine\Player;

class LandOption {
	/** @var Invitee[] */
	private $invitee = [];
	/** @var bool */
	private $allowTouch;
	/** @var bool */
	private $allowIn;
	/** @var bool */
	private $allowPickup;

	public function __construct(array $invitee, bool $allowTouch, bool $allowIn, bool $allowPickup) {
		if(!is_array($invitee))
			$invitee = [];
		$this->invitee = $invitee;

		$this->allowTouch = $allowTouch;
		$this->allowIn = $allowIn;
		$this->allowPickup = $allowPickup;
	}

	/**
	 * @return Invitee[]
	 */
	public function getAllInvitee(): array {
		return $this->invitee;
	}

	/**
	 * @param Player|string $player
	 * @return Invitee
	 */
	public function getInvitee($player): ?Invitee {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		foreach($this->invitee as $val) {
			if($val->getName() === $player) return $val;
		}
		return null;
	}

	public function isInvitee($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		foreach($this->invitee as $val) {
			if($val->getName() === $player) return true;
		}
		return false;
	}

	public function addInvitee(Invitee $invitee): bool {
		if($this->isInvitee($invitee->getName())) return false;

		$this->invitee[] = $invitee;
		return true;
	}

	public function getAllowTouch(): bool {
		return $this->allowTouch;
	}

	public function setAllowTouch(bool $val) {
		$this->allowTouch = $val;
	}

	public function getAllowIn(): bool {
		return $this->allowIn;
	}

	public function setAllowIn(bool $val) {
		return $this->allowIn = $val;
	}

	public function getAllowPickup(): bool {
		return $this->allowPickup;
	}

	public function setAllowPickup(bool $val) {
		return $this->allowPickup = $val;
	}
}

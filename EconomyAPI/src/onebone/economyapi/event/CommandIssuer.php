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

namespace onebone\economyapi\event;

use pocketmine\player\Player;

class CommandIssuer implements Issuer {
	/** @var Player */
	private $player;
	/** @var string */
	private $command;
	/** @var string */
	private $line;

	public function __construct(Player $player, string $command, string $line) {
		$this->player = $player;
		$this->command = $command;
		$this->line = $line;
	}

	public function getPlayer(): Player {
		return $this->player;
	}

	public function getCommand(): string {
		return $this->command;
	}

	public function getLine(): string {
		return $this->line;
	}

	public function __toString(): string {
		return "Command({$this->line}) from player {$this->player->getName()}";
	}
}

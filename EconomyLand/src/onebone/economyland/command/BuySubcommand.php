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

namespace onebone\economyland\command;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\LandMeta;
use onebone\economyland\land\LandOption;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector2;
use pocketmine\Player;

class BuySubcommand implements Subcommand {
	/** @var EconomyLand */
	private $plugin;
	/** @var SharedPosition */
	private $pos;

	public function __construct(EconomyLand $plugin, SharedPosition $pos) {
		$this->plugin = $plugin;
		$this->pos = $pos;
	}

	public function getName(): string {
		return "buy";
	}

	public function process(CommandSender $sender, array $args): void {
		if(!$sender instanceof Player) {
			$sender->sendMessage($this->plugin->getMessage('in-game-command'));
			return;
		}

		if(!$sender->hasPermission('economyland.command.land.buy')) {
			$sender->sendMessage($this->plugin->getMessage('no-permission'));
			return;
		}

		if($this->pos->hasPositions($sender)) {
			$start = $this->pos->getPosition(1, $sender);
			$end = $this->pos->getPosition(2, $sender);

			if($start[2] !== $end[2]) {
				$sender->sendMessage($this->plugin->getMessage('cannot-set-different-world'));
				return;
			}

			$startVec = new Vector2($start[0], $start[1]);
			$endVec = new Vector2($end[0], $end[1]);

			$land = $this->plugin->getLandManager()->createLand($startVec, $endVec, $sender->getLevel(), $sender,
				new LandOption([], false, true, false), new LandMeta(microtime(true)));
			$this->plugin->getLandManager()->addLand($land);

			// Renew land list in /land option, /land invite command usage
			$sender->sendCommandData();

			$size = $land->getEnd()->subtract($land->getStart());

			$sender->sendMessage($this->plugin->getMessage('bought-land', [
				$land->getId(), ($size->x + 1) * ($size->y + 1)
			]));
		}else{
			$sender->sendMessage($this->plugin->getMessage('set-position'));
		}
	}
}

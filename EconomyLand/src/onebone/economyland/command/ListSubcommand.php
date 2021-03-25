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
use pocketmine\command\CommandSender;
use pocketmine\Player;

class ListSubcommand implements Subcommand {
	private const ENTRIES_PER_PAGE = 5;

	private $plugin;

	public function __construct(EconomyLand $plugin) {
		$this->plugin = $plugin;
	}

	public function getName(): string {
		return "list";
	}

	public function getUsage(array $args): string {
		return "/land list [player] [page]";
	}

	public function process(CommandSender $sender, array $args): void {
		$player = array_shift($args);
		$page = trim(array_shift($args));
		if(trim($player) === '') {
			if($sender instanceof Player) {
				$sender->sendMessage($this->plugin->getMessage('list-command-help', [$this->getUsage($args)]));
				$player = $sender->getName();
			}else{
				$sender->sendMessage($this->plugin->getMessage('command-usage', [$this->getUsage($args)]));
				return;
			}
		}

		$matchPlayer = $this->plugin->getServer()->getPlayer($player);
		if($matchPlayer !== null) {
			$player = $matchPlayer->getName();
		}

		if(!is_numeric($page)) {
			$page = 1;
		}

		$mgr = $this->plugin->getLandManager();
		$lands = array_values($mgr->getLandsByOwner($player));

		$count = count($lands);
		$maxPage = ceil($count / 5);
		$page = max(1, min($page, $maxPage));

		$fromIndex = self::ENTRIES_PER_PAGE * ($page - 1);
		$toIndex = min($count, self::ENTRIES_PER_PAGE * $page);
		if($maxPage === 0 or $toIndex <= $fromIndex) {
			$sender->sendMessage($this->plugin->getMessage('no-list-entry', [$player]));
			return;
		}

		$sender->sendMessage($this->plugin->getMessage('list-header', [$player, $page, $maxPage]));
		for($i = $fromIndex; $i < $toIndex; ++$i) {
			$land = $lands[$i];

			$size = $land->getEnd()->subtract($land->getStart())->add(1, 1)->floor();
			$sender->sendMessage($this->plugin->getMessage('list-entry', [$land->getId(), $size->getX() * $size->getY()]));
		}
	}
}

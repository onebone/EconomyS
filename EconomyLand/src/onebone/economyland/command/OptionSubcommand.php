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
use onebone\economyland\form\LandOptionForm;
use onebone\economyland\internal\InternalUtils;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class OptionSubcommand implements Subcommand {
	/** @var EconomyLand */
	private $plugin;

	public function __construct(EconomyLand $plugin) {
		$this->plugin = $plugin;
	}

	public function getName(): string {
		return "option";
	}

	public function getUsage(array $args): string {
		return "/land option <part of land ID>";
	}

	public function process(CommandSender $sender, array $args): void {
		if(!$sender instanceof Player) {
			$sender->sendMessage($this->plugin->getMessage('in-game-command'));
			return;
		}

		if(!$sender->hasPermission('economyland.command.land.option')) {
			$sender->sendMessage($this->plugin->getMessage('no-permission'));
			return;
		}

		$id = trim(array_shift($args));
		if($id === '') {
			$sender->sendMessage($this->plugin->getMessage('command-usage', ['/land option <part of land ID>']));
			return;
		}

		$land = InternalUtils::getSingleUserLandVerbose($this->plugin, $sender, $id);
		if($land === null) return;

		$sender->sendForm(new LandOptionForm($this->plugin, $land));
	}
}

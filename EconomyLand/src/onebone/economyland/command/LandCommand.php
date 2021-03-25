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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class LandCommand extends Command implements PluginIdentifiableCommand {
	/** @var EconomyLand */
	private $plugin;
	/** @var Subcommand[] */
	private $subcommands = [];

	public function __construct(EconomyLand $plugin) {
		parent::__construct(
			"land",
			"Manage land",
			"/land <pos1|pos2|buy|here|option|invite> [args...]",
			[]
		);

		$permissions = [
			"economyland.command.land",
			"economyland.command.land.option",
			"economyland.command.land.move",
			"economyland.command.land.pos",
			"economyland.command.land.buy",
			"economyland.command.land.here",
			"economyland.command.land.invite",
			"economyland.command.land.invite.remove",
			"economyland.command.land.list"
		];
		$this->setPermission(implode(";", $permissions));

		$this->plugin = $plugin;

		$this->initSubcommands();
	}

	public function getPlugin(): Plugin {
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
		$subcommand = array_shift($args);
		if(isset($this->subcommands[$subcommand])) {
			$this->subcommands[$subcommand]->process($sender, $args);
			return true;
		}else{
			$sender->sendMessage($this->getUsage());
			return false;
		}
	}

	private function initSubcommands() {
		$sharedPosition = new SharedPosition();

		$this->subcommands = [
			"pos1" => new Pos1Subcommand($this->plugin, $sharedPosition),
			"pos2" => new Pos2Subcommand($this->plugin, $sharedPosition),
			"buy" => new BuySubcommand($this->plugin, $sharedPosition),
			"here" => new HereSubcommand($this->plugin),
			"invite" => new InviteSubcommand($this->plugin),
			"option" => new OptionSubcommand($this->plugin),
			"move" => new MoveSubcommand($this->plugin),
			"list" => new ListSubcommand($this->plugin)
		];
	}
}

// class to share players' set position between pos1, pos2, buy
class SharedPosition {
	private $pos1 = [];
	private $pos2 = [];

	public function setPosition(int $type, $player, Vector3 $position, string $worldName): void {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$object = [
			$position->getX(), $position->getZ(), $worldName
		];

		if($type === 1) {
			$this->pos1[$player] = $object;
		}else{
			$this->pos2[$player] = $object;
		}
	}

	public function hasPositions($player): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		return isset($this->pos1[$player]) and isset($this->pos2[$player]);
	}

	public function getPosition(int $type, $player): ?array {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		return $type === 1 ? ($this->pos1[$player] ?? null) : ($this->pos2[$player] ?? null);
	}
}

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

namespace onebone\economyapi;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\player\Player;

class EventListener implements Listener {
	private EconomyAPI $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;
	}

	public function onDataPacketSend(DataPacketSendEvent $event) {
		$packets = $event->getPackets();
		$targets = $event->getTargets();

		// only hook that points to a single player
		if(count($targets) !== 1) return;

		$player = $targets[0]->getPlayer();
		if($player === null) return;

		foreach($packets as $packet) {
			if($packet instanceof AvailableCommandsPacket) {
				$this->processAvailableCommandPacket($packet, $player);
			}
		}
	}

	private function processAvailableCommandPacket(AvailableCommandsPacket $pk, Player $player) {
		$currencies = CommandParameter::enum(
			name: 'currency ID',
			enum: new CommandEnum('currencies', array_keys($this->plugin->getCurrencies())),
			flags: AvailableCommandsPacket::ARG_TYPE_STRING,
			optional: true
		);

		$amount = CommandParameter::standard(
			name: 'amount',
			type: AvailableCommandsPacket::ARG_TYPE_FLOAT,
			optional: false
		);

		$players = CommandParameter::enum(
			name: 'players',
			enum: new CommandEnum('players', array_map(function(Player $player) {
				return $player->getName();
			}, $this->plugin->getServer()->getOnlinePlayers())),
			flags: AvailableCommandsPacket::ARG_TYPE_STRING,
			optional: false
		);

		if(isset($pk->commandData['mymoney'])) {
			$data = $pk->commandData['mymoney'];

			$data->overloads = [[$currencies]];

			$pk->commandData['mymoney'] = $data;
		}

		if(isset($pk->commandData['seemoney'])) {
			$data = $pk->commandData['seemoney'];

			$data->overloads = [[$players, $currencies]];

			$pk->commandData['seemoney'] = $data;
		}

		foreach(['setmoney', 'givemoney', 'takemoney'] as $command) {
			if(isset($pk->commandData[$command])) {
				$data = $pk->commandData[$command];

				$data->overloads = [[$players, $amount, $currencies]];

				$pk->commandData[$command] = $data;
			}
		}

		if(isset($pk->commandData['pay'])) {
			$data = $pk->commandData['pay'];

			$data->overloads = [
				[
					CommandParameter::enum(
						name: 'target',
						enum: new CommandEnum('player', array_filter(array_map(function(Player $player) {
							return $player->getName();
						}, $this->plugin->getServer()->getOnlinePlayers()), function($p) use ($player) {
							return $player->getName() !== $p;
						})),
						flags: AvailableCommandsPacket::ARG_TYPE_STRING
					),
					$amount, $currencies
				]
			];

			$pk->commandData['pay'] = $data;
		}

		if(isset($pk->commandData['economy'])) {
			$data = $pk->commandData['economy'];

			$data->overloads = [
				[
					CommandParameter::enum(
						name: 'property',
						enum: new CommandEnum('currency', ['currency']),
						flags: AvailableCommandsPacket::ARG_TYPE_STRING,
						optional: false
					),
					$currencies
				],
				[
					CommandParameter::enum(
						name: 'property',
						enum: new CommandEnum('language', ['language']),
						flags: AvailableCommandsPacket::ARG_TYPE_STRING,
						optional: false
					),
					CommandParameter::enum(
						name: 'language',
						enum: new CommandEnum('languages', $this->plugin->getLanguages()),
						flags: AvailableCommandsPacket::ARG_TYPE_STRING,
						optional: false
					),
				]
			];

			$pk->commandData['economy'] = $data;
		}
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function onPlayerJoin(PlayerJoinEvent $_) {
		foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->syncAvailableCommands();
		}
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function onPlayerQuit(PlayerQuitEvent $_) {
		foreach($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$player->getNetworkSession()->syncAvailableCommands();
		}
	}
}

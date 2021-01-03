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
	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;
	}

	public function onDataPacketSend(DataPacketSendEvent $event) {
		foreach($event->getPackets() as $pk) {
			if(!$pk instanceof AvailableCommandsPacket) return;

			$targets = $event->getTargets();

			// NetworkSession->syncAvailableCommands() sends to only one player,
			// so this method seems to select accurate player in most case.
			// As API 4.0.0 doesn't allow to hook packet per player, this can cause
			// wrong language for player when multi language on command usage is
			// implemented.
			if(count($targets) !== 1) return;

			$player = $targets[0]->getPlayer();
			if(!$player instanceof Player) {
				return;
			}

			$currencies = self::also(new CommandParameter(), function(CommandParameter $it) {
				$it->paramName = 'currency ID';
				$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->isOptional = true;
				$it->enum = new CommandEnum('currencies', array_keys($this->plugin->getCurrencies()));
			});
			$amount = self::also(new CommandParameter(), function(CommandParameter $it) {
				$it->paramName = 'amount';
				$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_FLOAT;
				$it->isOptional = false;
			});
			$players = self::also(new CommandParameter(), function(CommandParameter $it) {
				$it->paramName = 'players';
				$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->isOptional = false;
				$it->enum = new CommandEnum('players', array_map(function(Player $player) {
					return $player->getName();
				}, $this->plugin->getServer()->getOnlinePlayers()));
			});

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

				foreach($event->getTargets() as $session) {
					$player = $session->getPlayer();
					if($player !== null)
						$self[] = $player;
				}
				$data->overloads = [
					[
						self::also(new CommandParameter(), function(CommandParameter $it) use ($player) {
							$it->paramName = 'target';
							$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
							$it->isOptional = false;
							$it->enum = new CommandEnum('target', array_filter(array_map(function(Player $player){
								return $player->getName();
							}, $this->plugin->getServer()->getOnlinePlayers()), function($p) use ($player) {
								return $player->getName() !== $p;
							}));
						}),
						$amount, $currencies
					]
				];

				$pk->commandData['pay'] = $data;
			}

			if(isset($pk->commandData['economy'])) {
				$data = $pk->commandData['economy'];

				$data->overloads = [
					[
						self::also(new CommandParameter(), function(CommandParameter $it) {
							$it->paramName = 'property';
							$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
							$it->isOptional = false;
							$it->enum = new CommandEnum('currency', ['currency']);
						}),
						$currencies
					],
					[
						self::also(new CommandParameter(), function(CommandParameter $it) {
							$it->paramName = 'property';
							$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
							$it->isOptional = false;
							$it->enum = new CommandEnum('language', ['language']);
						}),
						self::also(new CommandParameter(), function(CommandParameter $it) {
							$it->paramName = 'language';
							$it->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
							$it->isOptional = false;
							$it->enum = new CommandEnum('languages', $this->plugin->getLanguages());
						})
					]
				];

				$pk->commandData['economy'] = $data;
			}
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

	public static function also($object, $block) {
		$block($object);
		return $object;
	}
}

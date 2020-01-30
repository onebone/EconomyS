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

namespace onebone\economyland;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\math\Vector2;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class EconomyLand extends PluginBase implements Listener {
	public const API_VERSION = 2;

	const FALLBACK_LANGUAGE = 'en';

	private $lang, $fallbackLang;
	/** @var EconomyAPI */
	private $api;
	/** @var PluginConfiguration */
	private $pluginConfig;
	/** @var Vector2[][] */
	private $pos = [];

	public function onEnable() {
		$this->saveDefaultConfig();
		$this->pluginConfig = new PluginConfiguration($this);

		$api = $this->getServer()->getPluginManager()->getPlugin('EconomyAPI');
		if(!$api instanceof EconomyAPI) {
			$this->getLogger()->warning('EconomyAPI is not loaded. EconomyLand will not be enabled because required plugin is not loaded.');
			return;
		}

		$this->api = $api;

		if(EconomyAPI::API_VERSION < 4) {
			$this->getLogger()->warning('Current applied version of EconomyAPI is outdated. Please update EconomyAPI.');
			$this->getLogger()->warning('Expected minimum API version: 4, got ' . EconomyAPI::API_VERSION);
			return;
		}

		$this->loadLanguages();
	}

	public function getMessage(string $key, array $params = []): string {
		if(isset($this->lang[$key])) {
			return $this->api->replaceParameters($this->lang[$key], $params);
		}elseif(isset($this->fallbackLang[$key])) {
			return $this->api->replaceParameters($this->fallbackLang[$key], $params);
		}

		return "There is no key named \"$key\"";
	}

	private function loadLanguages() {
		$lang = strtolower($this->pluginConfig->getLanguage());
		if(!in_array($lang, ['en'])) {
			$lang = self::FALLBACK_LANGUAGE;
		}

		$resource = $this->getResource('lang_' . $lang . '.json');
		if($resource === null) {
			$resource = $this->getResource('lang_en.json');
		}

		$this->lang = json_decode(stream_get_contents($resource), true);
		fclose($resource);

		$resource = $this->getResource('lang_en.json');
		$this->fallbackLang = json_decode(stream_get_contents($resource), true);
		fclose($resource);
	}

	public function getPluginConfiguration(): PluginConfiguration {
		return $this->pluginConfig;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		switch(array_shift($args)) {
			case 'pos1':
				if(!$sender instanceof Player) {
					$sender->sendMessage(TextFormat::RED . 'Please run this command in-game.');
					return true;
				}

				if(!$sender->hasPermission('economyland.command.land.pos')) {
					$sender->sendMessage(TextFormat::RED . 'You don\'t have permission to run this command.');
					return true;
				}

				$this->pos[$sender->getName()][0] = new Vector2($sender->x, $sender->z);
				$sender->sendMessage($this->getMessage('pos1-set'));
				return true;
			case 'pos2':
				if(!$sender instanceof Player) {
					$sender->sendMessage(TextFormat::RED . 'Please run this command in-game.');
					return true;
				}

				if(!$sender->hasPermission('economyland.command.land.pos')) {
					$sender->sendMessage(TextFormat::RED . 'You don\'t have permission to run this command.');
					return true;
				}

				$this->pos[$sender->getName()][1] = new Vector2($sender->x, $sender->z);
				$sender->sendMessage($this->getMessage('pos2-set'));
				return true;
		}
		return true;
	}
}

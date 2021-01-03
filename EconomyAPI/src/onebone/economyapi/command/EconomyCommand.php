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

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\form\CurrencySelectionForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;

class EconomyCommand extends Command implements PluginOwned {
	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;

		$desc = $plugin->getCommandMessage("economy");
		parent::__construct("economy", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.economy");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$this->testPermission($sender)) {
			return false;
		}

		$plugin = $this->plugin;

		$mode = strtolower(array_shift($args));
		$val = array_shift($args);

		switch($mode) {
			case 'lang':
			case 'language':
				if(trim($val) === "") {
					$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
					return true;
				}

				if($plugin->setPlayerLanguage($sender->getName(), $val)) {
					$sender->sendMessage($plugin->getMessage("language-set", $sender, [$val]));
				}else{
					$sender->sendMessage(TextFormat::RED . "There is no language such as $val");
				}
				return true;
			case 'currency':
				/** @var EconomyAPI $plugin */
				$plugin = $this->getOwningPlugin();

				if(trim($val) === '') {
					if(!$sender instanceof Player) {
						$sender->sendMessage($plugin->getMessage('economy-currency-specify', $sender));
						return true;
					}

					$sender->sendForm(new CurrencySelectionForm($plugin, $plugin->getCurrencies(), $sender));
					return true;
				}

				$currency = $plugin->getCurrency($val);
				if($currency === null) {
					$sender->sendMessage($plugin->getMessage('currency-unavailable', $sender, [$val]));
					return true;
				}

				if($plugin->setPlayerPreferredCurrency($sender, $currency)) {
					$sender->sendMessage($plugin->getMessage('economy-currency-set', $sender, [
						$currency->getName(), $currency->getSymbol()
					]));
				}else{
					$sender->sendMessage($plugin->getMessage('economy-currency-failed', $sender, [$currency->getName()]));
				}
				return true;
			default:
				$sender->sendMessage($this->getUsage());
		}

		$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
		return false;
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}
}

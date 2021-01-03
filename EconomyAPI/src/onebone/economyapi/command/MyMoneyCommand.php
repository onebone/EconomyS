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

use onebone\economyapi\currency\CurrencyReplacer;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;

class MyMoneyCommand extends Command implements PluginOwned {
	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;

		$desc = $plugin->getCommandMessage("mymoney");
		parent::__construct("mymoney", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.mymoney");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		if($sender instanceof Player) {
			$plugin = $this->plugin;

			$currencyId = array_shift($params);
			if($currencyId !== null) {
				$currency = $plugin->getCurrency($currencyId);

				if($currency === null) {
					$sender->sendMessage($plugin->getMessage('currency-unavailable', $sender, [$currencyId]));
					return true;
				}
			}else{
				$currency = $plugin->getPlayerPreferredCurrency($sender, false);
			}

			$money = $plugin->myMoney($sender, $currency);
			$sender->sendMessage($plugin->getMessage("mymoney-mymoney", $sender, [new CurrencyReplacer($currency, $money)]));

			if($currencyId === null) { // show all balance of each currency when currency is not specified
				foreach($plugin->getCurrencies() as $val) {
					if($val->isExposed() and $val !== $currency) {
						$money = $plugin->myMoney($sender, $val);
						if($money === false or $money === 0) continue;

						$sender->sendMessage($plugin->getMessage("mymoney-multiline", $sender, [
							$val->getName(), $val->getSymbol(), new CurrencyReplacer($val, $money)
						]));
					}
				}
			}
			return true;
		}
		$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
		return true;
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}
}


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
use onebone\economyapi\currency\CurrencyReplacer;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SetMoneyCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("setmoney");
		parent::__construct("setmoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.setmoney");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		$player = array_shift($params);
		$amount = array_shift($params);
		$currencyId = array_shift($params);

		if(!is_numeric($amount)) {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();
		if(($p = $plugin->getServer()->getPlayer($player)) instanceof Player) {
			$player = $p->getName();
		}

		if($currencyId === null) {
			$currency = $plugin->getPlayerPreferredCurrency($player, false);
		}else{
			$currencyId = trim($currencyId);
			$currency = $plugin->getCurrency($currencyId);
			if($currency === null) {
				$sender->sendMessage($plugin->getMessage('currency-unavailable', $sender, [$currencyId]));
				return true;
			}
		}

		if(!$plugin->hasAccount($player, $currency)) {
			if($plugin->hasAccount($player, $plugin->getDefaultCurrency())) {
				$plugin->createAccount($player, $currency);
				$sender->sendMessage($plugin->getMessage('account-created', $sender, [
					$player, $currency->getName(), $currency->getSymbol()
				]));
			}else{
				$sender->sendMessage($plugin->getMessage("player-never-connected", $sender, [$player]));
				return true;
			}
		}

		$result = $plugin->setMoney($player, $amount, $currency);
		switch ($result) {
			case EconomyAPI::RET_INVALID:
				$sender->sendMessage($plugin->getMessage("setmoney-invalid-number", $sender, [$amount]));
				break;
			case EconomyAPI::RET_NO_ACCOUNT:
				$sender->sendMessage($plugin->getMessage("player-never-connected", $sender, [$player]));
				break;
			case EconomyAPI::RET_UNAVAILABLE:
				$sender->sendMessage($plugin->getMessage("setmoney-unavailable", $sender));
				break;
			case EconomyAPI::RET_CANCELLED:
				$sender->sendMessage($plugin->getMessage("setmoney-failed", $sender));
				break;
			case EconomyAPI::RET_SUCCESS:
				$sender->sendMessage($plugin->getMessage("setmoney-setmoney", $sender, [
					$player,
					new CurrencyReplacer($currency, $amount)
				]));

				if($p instanceof Player) {
					$p->sendMessage($plugin->getMessage("setmoney-set", $p, [new CurrencyReplacer($currency, $amount)]));
				}
				break;
			default:
				$sender->sendMessage("WTF");
		}
		return true;
	}
}

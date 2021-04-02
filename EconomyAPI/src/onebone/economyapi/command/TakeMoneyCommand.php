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
use pocketmine\command\Command;
use onebone\economyapi\currency\CurrencyReplacer;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;

class TakeMoneyCommand extends Command implements PluginOwned {
	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;

		$desc = $plugin->getCommandMessage("takemoney");
		parent::__construct("takemoney", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.takemoney");
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

		$plugin = $this->plugin;
		if(($p = $plugin->getServer()->getPlayerByPrefix($player)) instanceof Player) {
			$player = $p->getName();
		}

		if($amount < 0) {
			$sender->sendMessage($plugin->getMessage("takemoney-invalid-number", $sender, [$amount]));
			return true;
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

		$result = $plugin->reduceMoney($player, $amount, $currency, null, false);
		switch ($result) {
			case EconomyAPI::RET_INVALID:
				$sender->sendMessage($plugin->getMessage("takemoney-player-lack-of-money", $sender, [
					$player,
					new CurrencyReplacer($currency, $amount),
					$plugin->myMoney($player)
				]));
				break;
			case EconomyAPI::RET_SUCCESS:
				$sender->sendMessage($plugin->getMessage("takemoney-took-money", $sender, [
					$player,
					new CurrencyReplacer($currency, $amount)
				]));

				if($p instanceof Player) {
					$p->sendMessage($plugin->getMessage("takemoney-money-taken", $sender, [new CurrencyReplacer($currency, $amount)]));
				}
				break;
			case EconomyAPI::RET_CANCELLED:
				$sender->sendMessage($plugin->getMessage("takemoney-failed", $sender));
				break;
			case EconomyAPI::RET_UNAVAILABLE:
				$sender->sendMessage($plugin->getMessage("takemoney-unavailable", $sender));
				break;
			case EconomyAPI::RET_NO_ACCOUNT:
				$sender->sendMessage($plugin->getMessage("player-never-connected", $sender, [$player]));
				break;
		}

		return true;
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}
}


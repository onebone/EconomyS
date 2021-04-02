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
use onebone\economyapi\form\AskPayForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;

class PayCommand extends Command implements PluginOwned {
	/** @var EconomyAPI */
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;

		$desc = $plugin->getCommandMessage("pay");
		parent::__construct("pay", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.pay");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		if(!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
			return true;
		}

		$player = array_shift($params);
		$amount = array_shift($params);
		$currencyId = array_shift($params);

		if(!is_numeric($amount)) {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		$plugin = $this->plugin;

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

		$money = $plugin->myMoney($sender, $currency);
		if($money < $amount) {
			$sender->sendMessage($plugin->getMessage("pay-no-money", $sender, [new CurrencyReplacer($currency, $amount)]));
			return true;
		}

		if(($p = $plugin->getServer()->getPlayerByPrefix($player)) instanceof Player) {
			$player = $p->getName();
		}

		if($player === $sender->getName()) {
			$sender->sendMessage($plugin->getMessage("pay-no-self", $sender));
			return true;
		}

		if(!$p instanceof Player and $plugin->getPluginConfig()->getAllowPayOffline() === false) {
			$sender->sendMessage($plugin->getMessage("player-not-connected", $sender, [$player]));
			return true;
		}

		if(!$plugin->hasAccount($player, $currency)) {
			$sender->sendMessage($plugin->getMessage("player-never-connected", $sender, [$player]));
			return true;
		}

		$sender->sendForm(new AskPayForm($plugin, $sender, $currency, $player, $amount, $label, $params));
		return true;
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}
}

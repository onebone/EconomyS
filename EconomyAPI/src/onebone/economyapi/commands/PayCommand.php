<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2015  onebone <jyc00410@gmail.com>
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

namespace onebone\economyapi\commands;

use pocketmine\Server;
use pocketmine\command\CommandSender;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\money\PayMoneyEvent;

class PayCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "pay"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <player> <amount>");
		$this->setPermission("economyapi.command.pay");
		$this->setDescription("Pay or give the money to the others");
	}
	
	public function exec(CommandSender $sender, array $params){
		$player = array_shift($params);
		$amount = array_shift($params);
		
		if(trim($player) === "" or trim($amount) === "" or !is_numeric($amount)){
			$sender->sendMessage("Usage: ".$this->getUsage());
			return true;
		}
		
		$server = Server::getInstance();
		
		$p = $server->getPlayer($player);
		if($p instanceof Player){
			$player = $p->getName();
		}
		
		if($player === $sender->getName()){
			$sender->sendMessage($this->getPlugin()->getMessage("pay-failed"));
			return true;
		}
		
		$result = $this->getPlugin()->reduceMoney($sender, $amount, false, "payment");
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage($this->getPlugin()->getMessage("pay-failed", $sender));
			return true;
		}
		$result = $this->getPlugin()->addMoney($player, $amount, false, "payment");
		if($result !== EconomyAPI::RET_SUCCESS){
			$sender->sendMessage($this->getPlugin()->getMessage("request-cancelled", $sender));
			$this->getPlugin()->addMoney($sender, $amount, true, "payment-rollback");
			return true;
		}
		$this->getPlugin()->getServer()->getPluginManager()->callEvent(new PayMoneyEvent($this->getPlugin(), $sender->getName(), $player, $amount));
		$sender->sendMessage($this->getPlugin()->getMessage("pay-success", $sender, [$amount, $player, "%3", "%4"]));
		if($p instanceof Player){
			$p->sendMessage($this->getPlugin()->getMessage("money-paid", $p, [$sender->getName(), $amount, "%3", "%4"]));
		}
		return true;
	}
}
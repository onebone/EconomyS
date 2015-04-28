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

use pocketmine\command\CommandSender;

use onebone\economyapi\EconomyAPI;

class ReturnDebtCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "returndebt"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <amount>");
		$this->setDescription("Returns debt from your account");
		$this->setPermission("economyapi.command.returndebt");
	}
	
	public function exec(CommandSender $sender, array $params){
		$amount = array_shift($params);
		
		if(trim($amount) === "" or (!is_numeric($amount) and $amount !== "all")){
			$sender->sendMessage("Usage: /".$this->getName()." <amount>");
			return true;
		}
		
		if($amount === "all"){
			$amount = $this->getPlugin()->myDebt($sender);
		}
		if($amount <= 0){
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-must-bigger-than-zero", $sender->getName()));
			return true;
		}
		
		if($this->getPlugin()->myMoney($sender) < $amount){
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-dont-have-money", $sender->getName(), array($amount, $this->getPlugin()->myMoney($sender), "%3", "%4")));
			return true;
		}
		
		$result = $this->getPlugin()->reduceDebt($sender, $amount, false, "ReturnDebtCommand");
		switch($result){
			case EconomyAPI::RET_INVALID:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-dont-have-debt", $sender->getName(), array($amount, $this->getPlugin()->myDebt($sender), "%3", "%4")));
			break;
			case EconomyAPI::RET_CANCELLED:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-failed", $sender->getName()));
			break;
			case EconomyAPI::RET_SUCCESS:
			$sender->sendMessage($this->getPlugin()->getMessage("returndebt-returndebt", $sender->getName(), array($amount, $this->getPlugin()->myDebt($sender), "%3", "%4")));
			break;
		}
		return true;
	}
}
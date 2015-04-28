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

class TakeDebtCommand extends EconomyAPICommand implements InGameCommand{
	public function __construct(EconomyAPI $plugin, $cmd = "takedebt"){
		parent::__construct($plugin, $cmd);
		$this->setUsage("/$cmd <amount>");
		$this->setPermission("economyapi.command.takedebt");
		$this->setDescription("Takes debt from plugin");

	}
	
	public function exec(CommandSender $sender, array $params){
		$amount = array_shift($params);
		
		if(trim($amount) === "" or !is_numeric($amount)){
			$cmd = $this->getName();
			$sender->sendMessage("Usage: /$cmd <amount>");
			return true;
		}
		$result = $this->getPlugin()->addDebt($sender, $amount, false, "TakeDebtCommand");
		$output = "";
		switch($result){
			case -4: // RET_ERROR_1
			$output .= $this->getPlugin()->getMessage("takedebt-over-range", $sender->getName(), array($amount, $this->getPlugin()->getConfigurationValue("debt-limit"), "%3", "%4"));
			break;
			case -3: // RET_ERROR2
			$output .= $this->getPlugin()->getMessage("takedebt-over-range-once", $sender->getName(), array($amount, $this->getPlugin()->getConfigurationValue("once-debt-limit"), "%3", "%4"));
			break;	
			case -2: // RET_CANCELLED
			$output .= $this->getPlugin()->getMessage("request-cancelled", $sender->getName());
			break;
			case 0: // RET_INVALID
			$output .= $this->getPlugin()->getMessage("takedebt-must-bigger-than-zero", $sender->getName());
			break;
			case 1: // RET_SUCCESS
			$output .= $this->getPlugin()->getMessage("takedebt-takedebt", $sender->getName(), array($amount, "%2", "%3", "%4"));
			break;
		}
		$sender->sendMessage($output);
		return true;
	}
}
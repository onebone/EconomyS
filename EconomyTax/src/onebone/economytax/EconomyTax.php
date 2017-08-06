<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
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

namespace onebone\economytax;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use onebone\economytax\task\PayTask;

use onebone\economyapi\EconomyAPI;

class EconomyTax extends PluginBase{

	/**
	 * @var EconomyAPI
	 */
	private $api;

	/**
	 * @var Config
	 */
	private $config;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		
		$this->api = EconomyAPI::getInstance();
		$this->config = new Config($this->getDataFolder()."tax.properties", Config::PROPERTIES, array(
			"time-for-tax" => 10,
			"tax-as-percentage" => "",
			"tax-as-money" => 100
		));
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new PayTask($this), $this->config->get("time-for-tax")*1200);
	}

	public function payTax(){
		if(($percent = $this->config->get("tax-as-percentage")) !== ""){
			$players = $this->getServer()->getOnlinePlayers();
			foreach($players as $player){
				if($player->hasPermission("economytax.tax.avoid")){
					continue;
				}
				$money = $this->api->myMoney($player);
				$taking = $money * ($percent / 100);
				$this->api->reduceMoney($player, min($money, $taking), true, "EconomyTax");
				$player->sendMessage("Your ".EconomyAPI::getInstance()->getMonetaryUnit()."$taking has taken by tax.");
			}
		}else{
			$money = $this->config->get("tax-as-money");
			$players = $this->getServer()->getOnlinePlayers();
				foreach($players as $player){
				if($player->hasPermission("economytax.tax.avoid")){
					continue;
				}
				$this->api->reduceMoney($player, min($this->api->myMoney($player), $money), true, "EconomyTax");
				$player->sendMessage("Your ".EconomyAPI::getInstance()->getMonetaryUnit()."$money has taken by tax.");
			}
		}
	}
}

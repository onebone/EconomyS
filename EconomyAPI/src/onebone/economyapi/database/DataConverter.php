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

namespace onebone\economyapi\database;

use pocketmine\utils\Config;

class DataConverter{
	private $moneyData, $version, $moneyFile;
	
	const VERSION_1 = 0x01;
	const VERSION_2 = 0x02;
	
	public function __construct($moneyFile){
		$this->parseData($moneyFile);
	}
	
	private function parseData($moneyFile){
		$moneyCfg = new Config($moneyFile, Config::YAML);
		$this->moneyFile = $moneyCfg;
		
		if($moneyCfg->exists("version")){
			$this->version = $moneyCfg->get("version");
		}else{
			$this->version = self::VERSION_1;
		}
		
		if($this->version === self::VERSION_1){
			$this->moneyData = $moneyCfg->get("money");
		}else{
			switch($this->version){
				case self::VERSION_2:
				$money = [];
				foreach($moneyCfg->get("money") as $player => $m){
					$money[strtolower($player)] = $m;
				}
				$this->moneyData = $money;
				break;
			}
		}
	}
	
	public function convertData($targetVersion){
		switch($this->version){
			case self::VERSION_1:
			switch($targetVersion){
				case self::VERSION_1:
				return true;
				
				case self::VERSION_2:
				$this->moneyFile->set("version", self::VERSION_2);
				
				$money = [];
				foreach($this->moneyData as $player => $m){
					$money[strtolower($player)] = $m;
				}
				
				$this->moneyFile->set("money", $money);
				
				$this->moneyFile->save();
				return true;
			}
			break;
			case self::VERSION_2:
			
			break;
		}
		return false;
	}
	
	public function getMoneyData(){
		return $this->moneyData;
	}
	
	public function getVersion(){
		return $this->version;
	}
}
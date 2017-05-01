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

namespace onebone\economyairport;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;

use onebone\economyapi\EconomyAPI;

class EconomyAirport extends PluginBase  implements Listener{
	private $airport;

	/**
	 * @var Config
	 */
	private $lang, $tag;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		$this->airport = array();

		$this->saveResource("language.properties");
		$this->saveResource("airport.yml");
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES);
		$this->tag = new Config($this->getDataFolder()."airport.yml", Config::YAML);

		$airportYml = new Config($this->getDataFolder()."AirportData.yml", Config::YAML);
		$this->airport = $airportYml->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable(){
		$airportYml = new Config($this->getDataFolder()."AirportData.yml", Config::YAML);
		$airportYml->setAll($this->airport);
		$airportYml->save();
	}

	public function onSignChange(SignChangeEvent $event){
		if(($data = $this->checkTag($event->getLine(0), $event->getLine(1))) !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			$block = $event->getBlock();
			switch($event->getLine(1)){
				case "departure":
					if(!is_numeric($event->getLine(2))){
						$player->sendMessage($this->getMessage("cost-must-be-numeric"));
						break;
					}
					if(trim($event->getLine(3)) === ""){
						$player->sendMessage($this->getMessage("no-target-airport"));
						break;
					}

					foreach($this->airport as $d){
						if($d["type"] === 1 and $d["name"] === $event->getLine(3)){
							$targetX = $d[0];
							$targetY = $d[1];
							$targetZ = $d[2];
							$targetLevel = $d[3];
							break;
						}
					}
					if(!isset($targetX)){
						$player->sendMessage($this->getMessage("no-arrival"));
						break;
					}
					$this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()] = array(
						"type" => 0,
						"cost" => ($cost = round($event->getLine(2))),
						"target" => $event->getLine(3),
						"targetX" => $targetX,
						"targetY" => $targetY,
						"targetZ" => $targetZ,
						"targetLevel" => $targetLevel
					);
					$mu = EconomyAPI::getInstance()->getMonetaryUnit();
					$event->setLine(0, str_replace("%MONETARY_UNIT%", $mu, $data[0]));
					$event->setLine(1, str_replace("%MONETARY_UNIT%", $mu, $data[1]));
					$event->setLine(2, str_replace(["%1", "%MONETARY_UNIT%"], [$cost, $mu], $data[2]));
					$event->setLine(3, str_replace(["%2", "%MONETARY_UNIT%"], [$event->getLine(3)], $data[3]));

					$player->sendMessage($this->getMessage("departure-created", [$event->getLine(3), $cost]));
					break;
				case "arrival":
					if(trim($event->getLine(2)) === ""){
						$player->sendMessage($this->getMessage("no-airport-name"));
						break;
					}
					if(strpos( $event->getLine(2), ":")){
						$player->sendMessage($this->getMessage("invalid-airport-name"));
						break;
					}
					$this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()] = array(
						$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getFolderName(),
						"name" => $event->getLine(2),
						"type" => 1
					);

					$player->sendMessage($this->getMessage("arrival-created", [$event->getLine(2), "%2"]));

					$event->setLine(0, $data[0]);
					$event->setLine(1, $data[1]);
					$event->setLine(2, str_replace("%1", $event->getLine(2), $data[2]));
					$event->setLine(3, "");
					break;
			}
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		if(isset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()])){
			$airport = $this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()];
			if($airport["type"] === 1)
				return;
				
			$player = $event->getPlayer();
			if(isset($this->airport[$airport["targetX"].":".$airport["targetY"].":".$airport["targetZ"].":".$airport["targetLevel"]]) and $this->airport[$airport["targetX"].":".$airport["targetY"].":".$airport["targetZ"].":".$airport["targetLevel"]]["type"] === 1){
				$money = EconomyAPI::getInstance()->myMoney($player);
				if(!$block->getLevel()->getTile(new Vector3($airport["targetX"], $airport["targetY"], $airport["targetZ"], $airport["targetLevel"])) instanceof Sign){
					$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
					unset($this->airport[$airport["target"]]);
					return;
				}
				if($money < $airport["cost"]){
					$player->sendMessage($this->getMessage("no-money", [$airport["cost"], $money]));
				}else{
					EconomyAPI::getInstance()->reduceMoney($player, $airport["cost"], true, "EconomyAirport");
					$level = $this->getServer()->getLevelByName($airport["targetLevel"]);
					$player->teleport(new Position($airport["targetX"], $airport["targetY"], $airport["targetZ"], $level));
					$time = $level->getTime();
					$day = (int)($time / Level::TIME_FULL);
					$time -= ($day * Level::TIME_FULL);
					$phrase = "sunrise";
					if($time < 1200){
						$phrase = "day";
					}elseif($time % Level::TIME_SUNSET < 2000){
						$phrase = "sunset";
					}elseif($time % Level::TIME_NIGHT < 9000){
						$phrase = "night";
					}
					$player->sendMessage($this->getMessage("thank-you", [$airport["target"], $level->getTime()." (". $phrase.")"]));
				}
			}else{
				$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		if(isset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()])){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.remove")){
				$player->sendMessage($this->getMessage("no-permission-break"));
				return;
			}
			unset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()]);
			$player->sendMessage($this->getMessage("airport-removed"));
		}
	}

	public function checkTag($firstLine, $secondLine){
		if(!$this->tag->exists($secondLine)){
			return false;
		}
		foreach($this->tag->get($secondLine) as $key => $data){
			if($firstLine === $key){
				return $data;
			}
		}
		return false;
	}

	public function getMessage($key, $value = ["%1", "%2"]){
		if($this->lang->exists($key)){
			return str_replace(["%1", "%2"], [$value[0], $value[1]], $this->lang->get($key));
		}else{
			return "Language with key \"$key\" does not exist";
		}
	}
}

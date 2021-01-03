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

namespace onebone\economyairport;

use onebone\economyapi\EconomyAPI;
use pocketmine\block\tile\Sign;
use pocketmine\block\utils\SignText;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class EconomyAirport extends PluginBase implements Listener {
	private $airport;

	/**
	 * @var Config
	 */
	private $lang, $tag;

	public function onEnable() {
		if(!file_exists($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}

		$this->airport = array();

		$this->saveResource("language.properties");
		$this->saveResource("airport.yml");
		$this->lang = new Config($this->getDataFolder() . "language.properties", Config::PROPERTIES);
		$this->tag = new Config($this->getDataFolder() . "airport.yml", Config::YAML);

		$airportYml = new Config($this->getDataFolder() . "AirportData.yml", Config::YAML);
		$this->airport = $airportYml->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable() {
		$airportYml = new Config($this->getDataFolder() . "AirportData.yml", Config::YAML);
		$airportYml->setAll($this->airport);
		$airportYml->save();
	}

	public function onSignChange(SignChangeEvent $event) {
		$text = $event->getNewText();

		if(($data = $this->checkTag($text->getLine(0), $text->getLine(1))) !== false) {
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.create")) {
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			$block = $event->getBlock();
			switch ($text->getLine(1)) {
				case "departure":
					if(!is_numeric($text->getLine(2))) {
						$player->sendMessage($this->getMessage("cost-must-be-numeric"));
						break;
					}
					if(trim($text->getLine(3)) === "") {
						$player->sendMessage($this->getMessage("no-target-airport"));
						break;
					}

					foreach($this->airport as $d) {
						if($d["type"] === 1 and $d["name"] === $text->getLine(3)) {
							$targetX = $d[0];
							$targetY = $d[1];
							$targetZ = $d[2];
							$targetLevel = $d[3];
							break;
						}
					}
					if(!isset($targetX)) {
						$player->sendMessage($this->getMessage("no-arrival"));
						break;
					}

					$pos = $block->getPos();
					$this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()] = array(
							"type" => 0,
							"cost" => ($cost = round($text->getLine(2))),
							"target" => $text->getLine(3),
							"targetX" => $targetX,
							"targetY" => $targetY,
							"targetZ" => $targetZ,
							"targetLevel" => $targetLevel
					);
					$mu = EconomyAPI::getInstance()->getMonetaryUnit();

					$event->setNewText(new SignText([
						str_replace("%MONETARY_UNIT%", $mu, $data[0]),
						str_replace("%MONETARY_UNIT%", $mu, $data[1]),
						str_replace(["%1", "%MONETARY_UNIT%"], [$cost, $mu], $data[2]),
						str_replace(["%2", "%MONETARY_UNIT%"], [$text->getLine(3)], $data[3])
					]));

					$player->sendMessage($this->getMessage("departure-created", [$text->getLine(3), $cost]));
					break;
				case "arrival":
					if(trim($text->getLine(2)) === "") {
						$player->sendMessage($this->getMessage("no-airport-name"));
						break;
					}
					if(strpos($text->getLine(2), ":")) {
						$player->sendMessage($this->getMessage("invalid-airport-name"));
						break;
					}
					$pos = $block->getPos();
					$this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()] = array(
							$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getWorld()->getFolderName(),
							"name" => $text->getLine(2),
							"type" => 1
					);

					$player->sendMessage($this->getMessage("arrival-created", [$text->getLine(2), "%2"]));

					$event->setNewText(new SignText([
						$data[0],
						$data[1],
						str_replace("%1", $text->getLine(2), $data[2])
					]));
					break;
			}
		}
	}

	public function checkTag($firstLine, $secondLine) {
		if(!$this->tag->exists($secondLine)) {
			return false;
		}
		foreach($this->tag->get($secondLine) as $key => $data) {
			if($firstLine === $key) {
				return $data;
			}
		}
		return false;
	}

	public function getMessage($key, $value = ["%1", "%2"]) {
		if($this->lang->exists($key)) {
			return str_replace(["%1", "%2"], [$value[0], $value[1]], $this->lang->get($key));
		}else{
			return "Language with key \"$key\" does not exist";
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event) {
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			return;
		}
		$block = $event->getBlock();
		$pos = $block->getPos();
		if(isset($this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()])) {
			$airport = $this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()];
			if($airport["type"] === 1)
				return;

			$player = $event->getPlayer();
			if(isset($this->airport[$airport["targetX"] . ":" . $airport["targetY"] . ":" . $airport["targetZ"] . ":" . $airport["targetLevel"]]) and $this->airport[$airport["targetX"] . ":" . $airport["targetY"] . ":" . $airport["targetZ"] . ":" . $airport["targetLevel"]]["type"] === 1) {
				$money = EconomyAPI::getInstance()->myMoney($player);
				if(!$pos->getWorld()->getTile(new Vector3($airport["targetX"], $airport["targetY"], $airport["targetZ"])) instanceof Sign) {
					$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
					unset($this->airport[$airport["target"]]);
					return;
				}
				if($money < $airport["cost"]) {
					$player->sendMessage($this->getMessage("no-money", [$airport["cost"], $money]));
				}else{
					EconomyAPI::getInstance()->reduceMoney($player, $airport["cost"], null, null, true);
					$level = $this->getServer()->getWorldManager()->getWorldByName($airport["targetLevel"]);
					$player->teleport(new Position($airport["targetX"], $airport["targetY"], $airport["targetZ"], $level));
					$time = $level->getTime();
					$day = (int) ($time / World::TIME_FULL);
					$time -= ($day * World::TIME_FULL);
					$phrase = "sunrise";
					if($time < 1200) {
						$phrase = "day";
					} elseif($time % World::TIME_SUNSET < 2000) {
						$phrase = "sunset";
					} elseif($time % World::TIME_NIGHT < 9000) {
						$phrase = "night";
					}
					$player->sendMessage($this->getMessage("thank-you", [$airport["target"], $level->getTime() . " (" . $phrase . ")"]));
				}
			}else{
				$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock();
		$pos = $block->getPos();
		if(isset($this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()])) {
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.remove")) {
				$player->sendMessage($this->getMessage("no-permission-break"));
				return;
			}
			unset($this->airport[$pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getWorld()->getFolderName()]);
			$player->sendMessage($this->getMessage("airport-removed"));
		}
	}
}

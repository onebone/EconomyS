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

namespace onebone\economyland\database;

use onebone\economyland\event\LandRemoveEvent;
use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\Player;

use onebone\economyland\event\LandAddedEvent;

class YamlDatabase implements Database{
	/**
	 * @var array
	 */
	private $land, $config;
	private $path;
	private $landNum = 0;


	public function __construct($fileName, $config, $otherName){
		$this->path = $fileName;
		$this->land = (new Config($fileName, Config::YAML))->getAll();
		if(count($this->land) > 0){
			$land = $this->land;
			$this->landNum = end($land)["ID"] + 1;
		}
		if(is_file($otherName)){
			$sq = new \SQLite3($otherName);
			$cnt = 0;
			$query = $sq->query("SELECT * FROM land");
			while(($d = $query->fetchArray(SQLITE3_ASSOC)) !== false){
				$invitee = [];
				$tmp = explode(SQLiteDatabase::INVITEE_SEPERATOR, $d["invitee"]);
				foreach($tmp as $t){
					$invitee[$t] = true;
				}
			/*	$this->land[$this->landNum] = [
					"ID" => $this->landNum++,
					"startX" => $d["startX"],
					"startZ" => $d["startZ"],
					"endX" => $d["endX"],
					"endZ" => $d["endZ"],
					"level" => $d["level"],
					"owner" => $d["owner"],
					"invitee" => $invitee,
					"price" => $d["price"],
					"expires" => $d["expires"]
				];*/
				$this->addLand($d["startX"], $d["endX"], $d["startZ"], $d["endZ"], $d["level"], $d["price"], $d["owner"], $d["expires"], $invitee);
				++$cnt;
			}
			$sq->close();
			Server::getInstance()->getLogger()->notice("[EconomyLand] Converted $cnt data into new database");
			@unlink($otherName);
		}
		$this->config = $config;
	}

	public function getByCoord($x, $z, $level){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		$x=floor($x);
		$z=floor($z);
		foreach($this->land as $land){
			if($level === $land["level"] and $land["startX"] <= $x and $land["endX"] >= $x and $land["startZ"] <= $z and $land["endZ"] >= $z){
				return $land;
			}
		}
		return false;
	}

	public function getAll(){
		return $this->land;
	}

	public function getLandById($id){
		return isset($this->land[$id]) ? $this->land[$id] : false;
	}

	public function getLandsByOwner($owner){
		$ret = [];
		foreach($this->land as $land){
			if($land["owner"] === $owner){
				$ret[] = $land;
			}
		}
		return $ret;
	}

	public function getLandsByKeyword($keyword){
		$ret = [];
		foreach($this->land as $land){
			if(stripos($keyword, $land["owner"] !== false) or stripos($land["owner"], $keyword) !== false){
				$ret[] = $land;
			}
		}
		return $ret;
	}

	public function getInviteeById($id){
		if(isset($this->land[$id])){
			return array_keys($this->land[$id]["invitee"]);
		}
		return false;
	}

	public function addInviteeById($id, $name){
		if(isset($this->land[$id])){
			$this->land[$id]["invitee"][$name] = true;
			return true;
		}
		return false;
	}

	public function removeInviteeByid($id, $name){
		if(isset($this->land[$id]["invitee"][$name])){
			unset($this->land[$id]["invitee"][$name]);
			return true;
		}
		return false;
	}

	public function addLand($startX, $endX, $startZ, $endZ, $level, $price, $owner, $expires = null, $invitee = []){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if($this->checkOverlap($startX, $endX, $startZ, $endZ, $level)){
			return false;
		}
		$this->land[$this->landNum] = [
			"ID" => $this->landNum,
			"startX" => $startX,
			"endX" => $endX,
			"startZ" => $startZ,
			"endZ" => $endZ,
			"price" => $price,
			"owner" => $owner,
			"level" => $level,
			"invitee" => [],
			"expires" => $expires
		];
		Server::getInstance()->getPluginManager()->callEvent(new LandAddedEvent($this->landNum, $startX, $endX, $startZ, $endZ, $level, $price, $owner, $expires));
		return $this->landNum++;
	}

	public function setOwnerById($id, $owner){
		if(isset($this->land[$id])){
			$this->land[$id]["owner"] = $owner;
			return true;
		}
		return false;
	}

	public function removeLandById($id){
		if(isset($this->land[$id])){
			Server::getInstance()->getPluginManager()->callEvent(($ev = new LandRemoveEvent($id)));
			if(!$ev->isCancelled()){
				unset($this->land[$id]);
				return true;
			}
		}
		return false;
	}

	public function canTouch($x, $z, $level, Player $player){
		foreach($this->land as $land){
			if($level === $land["level"] and $land["startX"] <= $x and $land["endX"] >= $x and $land["startZ"] <= $z and $land["endZ"] >= $z){
				if($player->getName() === $land["owner"] or isset($land["invitee"][$player->getName()]) or $player->hasPermission("economyland.land.modify.others")){ // If owner is correct
					return true;
				}else{ // If owner is not correct
					return $land;
				}
			}
		}
	//	return !in_array($level, $this->config["white-land"]) or $player->hasPermission("economyland.land.modify.whiteland");
		return -1; // If no land found
	}

	public function checkOverlap($startX, $endX, $startZ, $endZ, $level){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		foreach($this->land as $land){
			if($level === $land["level"]){
				if(($startX <= $land["endX"] and $endX >= $land["startX"]
				and $endZ >= $land["startZ"] and $startZ <= $land["endZ"])){
					return $land;
				}
			}
		}
		return false;
	}

	public function save(){
		$config = new Config($this->path, Config::YAML);
		$config->setAll($this->land);
		$config->save();
	}

	public function close(){
		$this->save();
	}
}

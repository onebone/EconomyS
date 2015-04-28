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

namespace onebone\economyland\database;

use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class SQLiteDatabase implements Database{
	/**
	 * @var \SQLite3
	 */
	private $land;
	private $config;

	const INVITEE_SEPERATOR = "\x01";
	public function __construct($fileName, $config, $otherName){
		$this->land = new \SQLite3($fileName);
		$this->land->exec("CREATE TABLE IF NOT EXISTS land(
			ID INTEGER PRIMARY KEY AUTOINCREMENT,
			startX INTEGER NOT NULL,
			startZ INTEGER NOT NULL,
			endX INTEGER NOT NULL,
			endZ INTEGER NOT NULL,
			level TEXT NOT NULL,
			owner TEXT NOT NULL,
			invitee TEXT NOT NULL,
			price INTEGER NOT NULL,
			expires INTEGER
		)");
		if(is_file($otherName)){
			$cnt = 0;
			$c = (new Config($otherName, Config::YAML))->getAll();
			foreach($c as $d){
			//	$this->land->exec("INSERT INTO land (startX, startZ, endX, endZ, level, owner, invitee, price, expires) VALUES ({$data[startX]}, {$data[startZ]}, {$data[endX]}, {$data[endZ]}, '{$data[level]}', '{$data[owner]}', ".implode(self::INVITEE_SEPERATOR, array_keys($data["invitee"])).", {$data[price]}, {$data[expires]})");
				$this->addLand($d["startX"], $d["endX"], $d["startZ"], $d["endZ"], $d["level"], $d["price"], $d["owner"], $d["expires"], implode(self::INVITEE_SEPERATOR, array_keys($data["invitee"])));
				++$cnt;
			}
			@unlink($otherName);
			Server::getInstance()->getLogger()->notice("[EconomyProperty] Converted $cnt data into new database");
		}
		$this->config = $config;
	}

	public function getByCoord($x, $z, $level){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		return $this->land->query("SELECT * FROM land WHERE (startX <= $x AND endX >= $x) AND (startZ <= $z AND endZ >= $z) AND $level")->fetchArray(SQLITE3_ASSOC);
	}

	public function getAll(){
		$result = $this->land->query("SELECT * FROM land");
		$ret = [];
		while(($ret[] = $result->fetchArray(SQLITE3_ASSOC)) !== false){}
		return $ret;
	}

	public function getLandById($id){
		return $this->land->query("SELECT * FROM land WHERE ID = $id")->fetchArray(SQLITE3_ASSOC);
	}

	public function getLandsByOwner($owner){
		$result = $this->land->query("SELECT * FROM land WHERE owner = '$owner'");
		$ret = [];
		while(($ret[] = $result->fetchArray(SQLITE3_ASSOC)) !== false){}
		return $ret;
	}

	public function getLandsByKeyword($keyword){
		$result = $this->land->query("SELECT * FROM land WHERE owner LIKE '%$keyword%'");
		$ret = [];
		while(($ret[] = $result->fetchArray(SQLITE3_ASSOC)) !== false){}
		return $ret;
	}

	public function getInviteeById($id){
		return explode(self::INVITEE_SEPERATOR, $this->land->query("SELECT invitee FROM land WHERE ID = $id")->fetchArray(SQLITE3_ASSOC)["invitee"]);
	}

	public function addInviteeById($id, $name){

	}

	public function removeInviteeByid($id, $name){

	}

	public function addLand($startX, $endX, $startZ, $endZ, $level, $price, $owner, $expires = null, $invitee = []){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		//$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, owner, level, price, invitee) VALUES ($startX, $endX, $startZ, $endZ, '$owner', '$level', $price, '')");
		$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, owner, level, price, invitee".($expires === null?"":", expires").") VALUES ($startX, $endX, $startZ, $endZ, '$player', '$level', $price, ','".($expires === null ? "":", $expires").")");
		return $this->land->query("SELECT seq FROM sqlite_sequence")->fetchArray(SQLITE3_ASSOC)["seq"] - 1;
	}

	public function setOwnerById($id, $owner){
		$this->land->exec("UPDATE land SET owner = '$owner' WHERE ID = $id");
	}

	public function removeLandById($id){
		$this->land->exec("DELETE FROM land WHERE ID = $id");
	}

	public function canTouch($x, $z, $level, Player $player){
		if(!is_bool($land = $this->land->query("SELECT owner,invitee FROM land WHERE level = '$level' AND endX >= $x AND endZ >= $z AND startX <= $x AND startZ <= $z")->fetchArray(SQLITE3_ASSOC))){
			if($player->getName() === $land["owner"] or stripos($player->getName().self::INVITEE_SEPERATOR, $land["invitee"]) or $player->hasPermission("economyland.land.modify.others")){
				return true;
			}else{
				return $land;
			}
		}
		//return !in_array($level, $this->config["white-land"]) or $player->hasPermission("economyland.land.modify.whiteland");
		return true;
	}

	public function checkOverlap($startX, $endX, $startZ, $endZ, $level){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		$result = $this->land->query("SELECT * FROM land WHERE startX <= $endX AND endX >= $endX AND startZ <= $endZ AND endZ >= $endZ AND level = '$level'")->fetchArray(SQLITE3_ASSOC);
		return !is_bool($result);
	}

	public function close(){
		$this->land->close();
	}
}
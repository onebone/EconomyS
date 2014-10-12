<?php

namespace onebone\economyland\database;

use pocketmine\level\Level;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\Player;

class YamlDatabase implements Database{
	/**
	 * @var array
	 */
	private $land, $config, $path;
	private $landNum = 0;


	public function __construct($fileName, $config, $otherName){
		$this->path = $fileName;
		$this->land = (new Config($fileName, Config::YAML))->getAll();
		$this->landNum = count($this->land);
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
			Server::getInstance()->getLogger()->notice("[EconomyProperty] Converted $cnt data into new database");
			@unlink($otherName);
		}
		$this->config = $config;
	}

	public function getByCoord($x, $z, $level){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		foreach($this->land as $land){
			if($level === $land["level"] and $land["startX"] < $x and $land["endX"] > $x and $land["startZ"] < $z and $land["endZ"] > $z){
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
			if(stripos($keyword, $land["owner"]) or stripos($land["owner"], $keyword)){
				$ret[] = $land;
			}
		}
		return $ret;
	}

	public function getInviteeById($id){
		if(isset($this->land[$id])){
			return $this->land[$id]["invitee"];
		}
		return false;
	}

	public function addInviteeById($id, $name){
		if(isset($this->land[$id])){
			$this->land[$id][$name] = true;
			return true;
		}
		return false;
	}

	public function removeInviteeByid($id, $name){
		if(isset($this->land[$id][$name])){
			unset($this->land[$id][$name]);
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
			unset($this->land[$id]);
			return true;
		}
		return false;
	}

	public function canTouch($x, $z, $level, Player $player){
		foreach($this->land as $land){
			if($level === $land["level"] and $land["startX"] < $x and $land["endX"] > $x and $land["startZ"] < $z and $land["endZ"] > $z){
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
			if((($land["startX"] < $startX and $land["endX"] > $startX) or ($land["startX"] < $endX and $land["endX"] > $endX)) and (($land["startZ"] < $startZ and $land["endZ"] > $startZ and $level === $land["level"]) or ($land["endZ"] < $endZ and $land["endZ"] > $endZ))){
				return true;
			}
		}
		return false;
	}

	public function close(){
		$config = new Config($this->path, Config::YAML);
		$config->setAll($this->land);
		$config->save();
	}
}
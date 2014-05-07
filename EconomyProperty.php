<?php

/*
__PocketMine Plugin__
name=EconomyProperty
version=1.0.4
author=onebone
apiversion=13
class=EconomyProperty
*/
/*

===============================
         CHANGE LOG
===============================

v1.0.0 : Initial release
v1.0.1 : Fixed security issue
v1.0.2 : Compatible with API 12 (Amai Beetroot)
v1.0.3 : Compatible with API 13 (Zekkou Cake)
v1.0.4 : Compatible with EconomyLand 1.4.5

*/

class EconomyProperty implements Plugin{
	private $api, $pos1, $pos2, $property, $tap;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->pos1 = array();
		$this->pos2 = array();
		$this->tap = array();
	}
	
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] Cannot find EconomyLand");
			$this->api->console->run("stop");
			return;
		}
		@mkdir(DATA_PATH."plugins/EconomyProperty");
		try{
			$this->property = new SQLite3(DATA_PATH."plugins/EconomyProperty/Properties.sqlite3");
			$this->property->exec(
				"CREATE TABLE IF NOT EXISTS Property(
					landNum INTEGER PRIMARY KEY AUTOINCREMENT,
					owner TEXT,
					price INTEGER,
					x INTEGER,
					y INTEGER,
					z INTEGER,
					level TEXT,
					startX INTEGER,
					startZ INTEGER,
					landX INTEGER,
					landZ INTEGER
				);"
			);
		}catch(Exception $e){
			console("[ERROR] Unknown error has been occurred during opening the data file. Error : ".$e);
			return;
		}
		$this->api->console->register("property", "<pos1 | pos2 | make>", array($this, "onHandleCommand"));
		$this->api->addHandler("player.block.touch", array($this, "onTouch"));
		$this->api->addHandler("console.command", array($this, "onIssueCommand"));
		EconomyPropertyAPI::set($this); // Can access EconomyProperty more easier and faster
	}
	
	public function __destruct(){}
	
	public function onHandleCommand($cmd, $param, $issuer){
		$output = "[EconomyProperty] ";
		switch($cmd){
			case "property":
			if(!$issuer instanceof Player){
				return "Please run this command in-game.\n";
			}
			$sub = array_shift($param);
			switch($sub){
				case "pos1":
				$this->pos1[$issuer->username] = array(
					(int)$issuer->entity->x,
					(int)$issuer->entity->y,
					(int)$issuer->entity->z,
					$issuer->level->getName()
				);
				$this->pos2[$issuer->username] = null;
				unset($this->pos2[$issuer->username]);
				$output .= "First position set.";
				break;
				case "pos2":
				if(!isset($this->pos1[$issuer->username])){
					$output .= "Please set first position first";
					break;
				}
				if($this->pos1[$issuer->username][3] !== $issuer->level->getName()){
					$output .= "Please set position in the same level.";
					break;
				}
				$this->pos2[$issuer->username] = array(
					(int)$issuer->entity->x,
					(int)$issuer->entity->y,
					(int)$issuer->entity->z,
				);
				$output .= "Second position set.";
				break;
				case "make":
				$price = array_shift($param);
				if(trim($price) == ""){
					$output .= "Usage : /property make <price>";
					break;
				}
				if(!is_numeric($price)){
					$output .= "Price must be number.";
					break;
				}
				if(!isset($this->pos1[$issuer->username]) or !isset($this->pos2[$issuer->username])){
					$output .= "Please set first and second position first.";
					break;
				}
				$level = $this->api->level->get($this->pos1[$issuer->username][3]);
				if(!$level instanceof Level){
					$output .= "The level you are finding is corrupted.";
					unset($this->pos1[$issuer->username]);
					unset($this->pos2[$issuer->username]);
					break;
				}
				$first = $this->pos1[$issuer->username];
				$sec = $this->pos2[$issuer->username];
				if($first[0] > $sec[0]){
					$temp = $sec[0];
					$sec[0] = $first[0];
					$first[0] = $temp;
				}
				if($first[2] > $sec[2]){
					$temp = $sec[2];
					$sec[2] = $first[2];
					$first[2] = $temp;
				}
				$first[0]--;
				$sec[0]++;
				$first[2]--;
				$sec[2]++;
				$d = $this->property->query("SELECT * FROM Property WHERE (((startX <= $first[0] AND landX >= $first[0]) AND (startZ <= $first[2] AND landZ >= $first[2])) OR ((startX <= $sec[0] AND landX >= $sec[0]) AND (startZ <= $first[2] AND landZ >= $sec[2]))) AND level = '$first[3]'")->fetchArray(SQLITE3_ASSOC);
				if(!is_bool($d)){
					$output .= "You are trying to overlap with other property.";
					break 2;
				}
				$centerx = (int) $first[0] + round((($sec[0] - $first[0]) / 2));
				$centerz = (int) $first[2] + round((($sec[2] - $first[2]) / 2));
				$x = (int) round(($sec[0] - $first[0]));
				$z = (int) round(($sec[2] - $first[2]));
				$y = 0;
				for(; $y < 127; $y++){
					if($level->getBlock(new Vector3($centerx, $y, $centerz))->getID() === AIR){
						break;
					}
				}
				if($y >= 127){
					$y = (int) $issuer->entity->y;
					$level->setBlock(new Vector3($centerx, $y, $centerz), BlockAPI::get(AIR));
				}
				$level->setBlock(new Vector3($centerx, $y, $centerz), BlockAPI::get(SIGN_POST));
				$info = $this->property->query("SELECT seq FROM sqlite_sequence")->fetchArray(SQLITE3_ASSOC);
				$entity = $this->api->tile->addSign($level, $centerx, $y, $centerz, array(
					"[PROPERTY]", 
					"Price : ".$price,
					"Blocks : ".($x * $z * 128),
					"Property #".($info["seq"])
				));
				$packet = new UpdateBlockPacket;
				$packet->x = $centerx;
				$packet->y = $y;
				$packet->z = $centerz;
				$packet->block = SIGN_POST;
				$packet->meta = 0;
				$this->api->player->broadcastPacket($this->api->player->getAll($level), $packet);
				$entity->data["creator"] = $issuer->username;
				$this->api->tile->spawnToAll($entity);
				$this->property->exec("INSERT INTO Property (owner, price, x, y, z, level, startX, startZ, landX, landZ) VALUES ('{$issuer->username}', $price, $centerx, $y, $centerz, '$first[3]', {$first[0]}, {$first[2]}, {$sec[0]}, {$sec[2]});");
				$output .= "Has been created property.";
				unset($this->pos1[$issuer->username]);
				unset($this->pos2[$issuer->username]);
				break;
				default:
				$output .= "Usage: /property <pos1 | pos2 | make>";
			}
			break;
		}
		return $output;
	}
	
	public function onTouch($data){
		$result = $this->property->query("SELECT * FROM Property WHERE startX <= {$data["target"]->x} AND landX >= {$data["target"]->x} AND startZ <= {$data["target"]->z} AND landZ >= {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
		if(!is_bool($result)){
			if($data["target"]->getID() == 323 or $data["target"]->getID() == 68 or $data["target"]->getID() == 63){
				$info = $this->property->query("SELECT * FROM Property WHERE x = {$data["target"]->x} AND y = {$data["target"]->y} AND z = {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)) goto check;
				if(!isset($this->tap[$data["player"]->username]) or $data["target"]->x.":".$data["target"]->y.":".$data["target"]->z !== $this->tap[$data["player"]->username]){
					$this->tap[$data["player"]->username] = $data["target"]->x.":".$data["target"]->y.":".$data["target"]->z;
					$this->api->schedule(30, array($this, "deleteTap"), $data["player"]->username);
					$data["player"]->sendChat("Are you sure to buy this? Tap again to confirm.");
					return false;
				}
				$money = $this->api->economy->mymoney($data["player"]);
				// $this->api->economy->useMoney($data["player"], $info["price"]);
				if($money < $info["price"]){
					$data["player"]->sendChat("[EconomyProperty] You don't enough money to buy this property");
					return false;
				}
				$result = EconomyLandAPI::$a->addLand(array(
					"endX" => $info["landX"],
					"endZ" => $info["landZ"],
					"startX" => $info["startX"],
					"startZ" => $info["startZ"],
					"price" => $info["price"],
					"owner" => $data["player"]->username,
					"level" => $info["level"]
				));
				if(!$result){
					$data["player"]->sendChat("[EconomyProperty] You're trying to buy more than land limit");
					return false;
				}
				$this->api->economy->useMoney($data["player"], $info["price"]);
				$level = $this->api->level->get($info["level"]);
				if($level instanceof Level){
					$tile = $this->api->tile->get(new Position($info["x"], $info["y"], $info["z"], $level));
					if($tile !== false){
						$this->api->tile->remove($tile->id);
					}
					$level->setBlock(new Vector3($info["x"], $info["y"], $info["z"]), BlockAPI::get(AIR));
				}
				$this->property->exec("DELETE FROM Property WHERE landNum = {$info["landNum"]}");
				$data["player"]->sendChat("[EconomyProperty] Has been bought land");
				return false;
			}
			check:
			if($this->api->ban->isOp($data["player"]->username)){
				if($data["target"]->x == $result["x"] and $data["target"]->y == $result["y"] and $data["target"]->z == $result["z"] and $data["target"]->level->getName() == $result["level"]){
					if($data["type"] == "break"){
						$this->property->exec("DELETE FROM Property WHERE landNum = $result[landNum]");
						$data["player"]->sendChat("The property has been removed.");
						return;
					}
				}
			}else{
				$data["player"]->sendChat("You don't have permissions to edit property.");
				return false;
			}
		}
	}
	
	public function onIssueCommand($data){
		if($data["cmd"] == "property" and !$data["issuer"] instanceof Player){
			return false;
		}
	}
	
	public function editPropertyData($data){ // Preparing...
		$info = $this->property->query("SELECT * FROM Property WHERE x = {$data["x"]} AND y = {$data["y"]} AND z = {$data["z"]} AND level = '{$data["level"]}'")->fetchArray(SQLITE3_ASSOC);
		if(is_bool($info)) return false;
		$info["owner"] = isset($data["owner"]) ? $data["owner"] : $info["owner"];
		$info["startX"] = isset($data["startX"]) ? $data["startX"] : $info["startX"];
		$info["startZ"] = isset($data["startZ"]) ? $data["startZ"] : $info["startZ"];
		$info["landX"] = isset($data["endX"]) ? $data["endX"] : $info["landX"];
		$info["landZ"] = isset($data["endZ"]) ? $data["endZ"] : $info["landZ"];
		return true;
	}
	
	public function deleteTap($username){
		$this->tap[$username] = null;
		unset($this->tap[$username]);
	}
}

class EconomyPropertyAPI{
	public static $object;
	
	public static function set(EconomyProperty $obj){
		self::$object = $obj;
	}
}
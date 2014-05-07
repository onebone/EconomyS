<?php
/*
__PocketMine Plugin__
name=EconomyAirport
version=1.1.0
author=onebone
apiversion=12,13
class=EconomyAirport
*/
/*
CHANGE LOG
================

V 1.0.0
Initial Release

V 1.0.1
Added config

V 1.0.2
Korean now avaliable

V 1.0.3
Korean invalid bug fixed

V 1.0.4
No OP can create airport bug fix
Multi World now avaliable

V 1.0.5
Code edit

V1.0.6
Many bugs patch

V1.0.7
Added accident

V1.0.8
Texts changes immediately

V1.0.9
Now works at DroidPocketMine

V1.0.10
Compatible with API 11

V1.0.11
Compatible with API 12 (Amai Beetroot)

V1.0.12
- Compatible with API 13 (Zekkou Cake)
- Fixed some code

V1.1.0
- Rewrote codes
*/

class EconomyAirport implements Plugin{
	private $api, $airport, $lang, $id, $departureSign, $arrivalSign;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] Cannot find EconomyAPI");
			$this->api->console->defaultCommands("stop", array(), "plugin", false);
			return;
		}
		@mkdir(DATA_PATH."plugins/EconomyAirport");
		$this->airport = new SQLite3(DATA_PATH."plugins/EconomyAirport/Airport.sqlite3");
		$this->airport->exec("CREATE TABLE IF NOT EXISTS arrival(
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			x INTEGER NOT NULL,
			y INTEGER NOT NULL,
			z INTEGER NOT NULL,
			name TEXT NOT NULL,
			level TEXT NOT NULL
		);
		CREATE TABLE IF NOT EXISTS departure(
			id INTEGER PRIMARY KEY AUTOINCREMENT ,
			x INTEGER NOT NULL,
			y INTEGER NOT NULL,
			z INTEGER NOT NULL,
			price INTEGER NOT NULL,
			target TEXT NOT NULL,
			level TEXT NOT NULL
		);");
		$this->api->event("tile.update", array($this, "onTileUpdate"));
		$this->api->addHandler("player.block.touch", array($this, "onTouch"));
		$this->createMessageConfig();
		$this->createSignConfig();
		$this->api->economy->EconomySRegister("EconomyAirport");
	}
	
	public function __destruct(){}
	
	private function createMessageConfig(){
		$this->lang = new Config(DATA_PATH."plugins/EconomyAirport/language.properties", CONFIG_PROPERTIES, array(
			"line3-must-numeric" => "Line 3 must be numeric",
			"no-permission" => "You don't have permission to create airport",
			"created-departure" => "Created departure to %1",
			"created-arrival" => "Created airport %1",
			"no-arrival" => "There are no airport named \"%1\"",
			"no-money" => "You don't have \$%1",
			"break-no-permission" => "You don't have permission to break the airport",
			"wrong-format" => "Please write in correct format"
		));
	}
	
	private function createSignConfig(){
		$this->id = new Config(DATA_PATH."plugins/EconomyAirport/Identifier.properties", CONFIG_PROPERTIES, array(
			"departure-id" => "departure",
			"arrival-id" => "arrival",
		));
		$this->departureSign = new Config(DATA_PATH."plugins/EconomyAirport/DepartureSign.yml", CONFIG_YAML, array(
			"airport" => array(
				"[AIRPORT]",
				"DEPARTURE", // Identifier
				"$%1", // Price
				"%2" // Arrival
			)
		));
		$this->arrivalSign = new Config(DATA_PATH."plugins/EconomyAirport/ArrivalSign.yml", CONFIG_YAML, array(
			"airport" => array(
				"[AIRPORT]",
				"ARRIVAL", // Identifier
				"%1", // Airport name
				"Airport"
			)
		));
	}
	
	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3"), array($val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "No message found named \"$key\"";
	}
	
	public function getData($line1, $line2){
		switch($line2){
			case $this->id->get("departure-id"):
			foreach($this->departureSign->getAll() as $key => $val){
				if($key === $line1){
					return $val;
				}
			}
			break;
			case $this->id->get("arrival-id"):
			foreach($this->arrivalSign->getAll() as $key => $val){
				if($key === $line1){
					return $val;
				}
			}
			break;
		}
		return false;
	}
	
	public function onTileUpdate($data){
		if($data->class === TILE_SIGN){
			if($data->data["Text1"] === "" or $data->data["Text2"] === "" or $data->data["Text3"] === ""){
				return;
			}
			$result = $this->getData($data->data["Text1"], $data->data["Text2"]);
			if($result === false){
				return;
			}
			$player = $this->api->player->get($data->data["creator"], false);
			if(!$this->api->ban->isOp($player->iusername)){
				$player->sendChat($this->getMessage("no-permission"));
				return;
			}
			if($this->id->get("departure-id") === $data->data["Text2"]){
				if(!is_numeric($data->data["Text3"])){
					$player->sendChat($this->getMessage("line3-must-numeric"));
					return;
				}
			}
			switch($data->data["Text2"]){
				case $this->id->get("departure-id"):
				if($data->data["Text4"] === ""){
					$player->sendChat($this->getMessage("wrong-format"));
					return;
				}
				$this->airport->exec("INSERT INTO departure (x, y, z, price, target, level) VALUES ({$data->x}, {$data->y}, {$data->z}, {$data->data["Text3"]}, '{$data->data["Text4"]}', '{$data->level->getName()}')");
				$data->setText(
					$result[0],
					$result[1],
					str_replace("%1", $data->data["Text3"], $result[2]),
					str_replace("%2", $data->data["Text4"], $result[3])
				);
				$player->sendChat($this->getMessage("created-departure", array($data->data["Text4"], "%2", "%3")));
				break;
				case $this->id->get("arrival-id"):
				$this->airport->exec("INSERT INTO arrival (x, y, z, name, level) VALUES ({$data->x}, {$data->y}, {$data->z}, '{$data->data["Text3"]}', '{$data->level->getName()}');");
				$data->setText(
					$result[0],
					$result[1],
					str_replace("%1", $data->data["Text3"], $result[2]),
					$result[3]
				);
				$player->sendChat($this->getMessage("created-arrival", array($data->data["Text3"], "%2", "%3")));
				break;
			}
		}
	}
	
	public function onTouch($data){
		$signArr = array(
			63,
			68,
			323
		);
		if(!in_array($data["target"]->getID(), $signArr)){
			return;
		}
		$departureInfo = $this->airport->query("SELECT * FROM departure WHERE x = {$data["target"]->x} AND y = {$data["target"]->y} AND z = {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
		$arrivalInfo = $this->airport->query("SELECT * FROM arrival WHERE level = '{$data["target"]->level->getName()}' AND name = '{$departureInfo["target"]}'")->fetchArray(SQLITE3_ASSOC);
		$arrival = $this->airport->query("SELECT * FROM arrival WHERE x = {$data["target"]->x} AND y = {$data["target"]->y} AND z = {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
		if(is_bool($departureInfo) and is_bool($arrival)){
			return;
		}
		if($data["type"] === "break"){
			if($this->api->ban->isOp($data["player"]->iusername)){
				$this->airport->exec("DELETE FROM ".(is_bool($departureInfo) ? "arrival":"departure")." WHERE x = {$data["target"]->x} AND y = {$data["target"]->y} AND z = {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'");
				return;
			}else{
				$data["player"]->sendChat($this->getMessage("break-no-permission"));
				return false;
			}
		}
		if(is_bool($arrivalInfo)){
			$data["player"]->sendChat($this->getMessage("no-arrival", array($departureInfo["target"], "%2", "%3")));
			return;
		}
		$can = $this->api->economy->useMoney($data["player"], $departureInfo["price"]);
		if(!$can){
			$data["player"]->sendChat($this->getMessage("no-money", array($departureInfo["price"], "%2", "%3")));
			return false;
		}else{
			$data["player"]->teleport(new Vector3($arrivalInfo["x"], $arrivalInfo["y"], $arrivalInfo["z"]));
			return false;
		}
	}
}
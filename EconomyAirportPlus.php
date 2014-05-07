<?php
/*
__PocketMine Plugin__
name=EconomyAirportPlus
version=1.1.0
author=onebone
apiversion=12,13
class=EconomyAirportPlus
*/

/*
CHANGE LOG
===============

V 1.0.0 : Initial Release

V 1.0.1 : Korean now avaliable

V 1.0.2 : Korean invalid bug fix

V 1.0.3 : Not OP can create airport bug fix

V1.0.4 : Over than two arrival at one world not avaliable

V1.0.5 : Added something

V1.0.6 : Texts changes immediately

V1.0.7 : Now works at DroidPocketMine

V1.0.8 : Compatible with API 11

V1.0.9 : Compatible with API 12 (Amai Beetroot)

V1.1.0 : More stable
*/

class EconomyAirportPlus implements Plugin {
	private $api, $arrival, $departure;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->arrival = array();
		$this->departure = array();
	}
	public function init(){
		$this->isFirst = true;
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI not found");
			$this->api->console->defaultCommands("stop", "", "EconomyAirportPlus", false);
			return;
		}
		$this->path = $this->api->plugin->configPath($this);
		$this->api->event("tile.update", array($this, "handler"));
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.block.touch", array($this, "handler"));
		$this->departuredata = new Config($this->path."Departure.yml", CONFIG_YAML);
		$this->arrivaldata = new Config($this->path."Arrival.yml", CONFIG_YAML);
		$this->refreshAirport();
		$this->api->economy->EconomySRegister("EconomyAirportPlus");
	}
	
	public function __destruct(){}

	public function refreshAirport(){
		if($this->isFirst == false){
			$this->departuredata->setAll($this->departure);
			$this->departuredata->save();
		}
		$departure = $this->api->plugin->readYAML($this->path."Departure.yml");
		if(is_array($departure)){
			foreach($departure as $d){
				$this->departure[] = array("x" => $d["x"], "y" => $d["y"], "z" => $d["z"], "cost" => $d["cost"], "arrival" => $d["arrival"], "level" => $d["level"]);
			}
		}
		if($this->isFirst == true){
			$this->isFirst = false;
		}else{
			$this->arrivaldata->setAll($this->arrival);
			$this->arrivaldata->save();
		}
		$arrival = $this->api->plugin->readYAML($this->path."Arrival.yml");
		if(is_array($arrival)){
			foreach($arrival as $a){
				$this->arrival[] = array("x" => $a["x"], "y" => $a["y"], "z" => $a["z"], "name" => $a["name"], "level" => $a["level"]);
			}
		}
	}
	
	public function handler(&$data, $event){
		$output = "";
		switch ($event){
		case "tile.update":
			if($data->class === TILE_SIGN){
				if($data->data["Text1"] == "international" or $data->data["Text1"] == "국제공항"){
					$player = $this->api->player->get($data->data["creator"], false);
					$lang = $data->data["Text1"] == "international" ? "english" : "korean";
					if($this->api->ban->isOp($player->username) == false){
						if($lang == "english"){
							$player->sendChat("[EconomyAirportPlus] Permission denied");
							break;
						}else{
							$player->sendChat("당신은 공항을 생성할 권한이 없습니다");
							break;
						}
					}
					if($data->data["Text2"] == "arrival" or $data->data["Text2"] == "도착"){
						if(is_array($this->arrival)){
							foreach($this->arrival as $a){
								if($a["level"] == $data->level->getName()){
									$output .= $lang == "english" ? "Same arrival at this world exist" : "이 월드에 이미 도착지가 존재합니다";
									$player->sendChat($output);
									break 2;
								}
							}
						}
						if($lang == "english"){
							$data->setText("[INTERNATIONAL]", "ARRIVAL", $data->level->getName(), "intl' Airport");
							$this->createArrival(array("x" => $data->data["x"], "y" => $data->data["y"], "z" => $data->data["z"], "name" => $data->data["Text3"], "level" => $data->level->getName()));
							$output .= "Arrival created";
						}else{
							if(is_array($this->arrival)){
								foreach($this->arrival as $a){
									if($a["level"] == $data->level->getName()){
										$output .= $lang == "english" ? "Same arrival at this world exist" : "이 월드에 이미 도착지가 존재합니다";
										$player->sendChat($output);
										break 2;
									}
								}
							}
							$data->setText("[국제공항]", "도착", $data->level->getName(), "intl' Airport");
							$this->createArrival(array("x" => $data->data["x"], "y" => $data->data["y"], "z" => $data->data["z"], "name" => $data->data["Text3"], "level" => $data->level->getName()));
							$output .= "국제공항이 생성되었습니다.";
						}
						$player->sendChat($output);
						break;
					}elseif($data->data["Text2"] == "departure" or $data->data["Text2"] == "출발"){
						if($data->data["Text3"] == "" or $data->data["Text4"] == ""){
							if($lang == "english"){
								$output .= "Incorrect airport data";
							}else{
								$output .= "공항의 데이터가 올바르지 않습니다.";
							}
							$player->sendChat($output);
							break;
						}

						$this->createDeparture(array("x" => $data->data["x"], "y" => $data->data["y"], "z" => $data->data["z"], "arrival" => $data->data["Text4"], "cost" => $data->data["Text3"], "level" => $data->level->getName()));
						if($lang == "english"){
							$data->setText("[INTERNATIONAL]", "DEPARTURE", $data->data["Text3"]."$", "To ".$data->data["Text4"]);
							$output .= "Airport created";
						}else{
							$data->setText("[국제공항]", "출발", $data->data["Text3"]."$", $data->data["Text4"]."행");
							$output .= "출발지가 생성되었습니다.";
						}
						$player->sendChat($output);
					}else{
						if($lang == "english"){
							$output .= "Incorrect airport data";
						}else{
							$output .= "공항의 데이터가 올바르지 않습니다.";
						}
						$player->sendChat($output);
					}
				}
			}
			break;
		case "player.block.touch":
			$player = $data["player"];
			switch ($data["type"]){
			case "break":
				if($data["target"]->getID() == 323 or $data["target"]->getID() == 63 or $data["target"]->getID() == 68){
					foreach($this->arrival as $a){
						if($a["x"] == $data["target"]->x and $a["y"] == $data["target"]->y and $a["z"] == $data["target"]->z and $data["target"]->level->getName() == $a["level"]){
							if($this->api->ban->isOp($data["player"]->username) === false){
								$output .= "You don't have permission to destroy airport";
								$player->sendChat($output);
								$player->close("tried to destroy airport");
								return false;
							}
							foreach($this->arrival as $key => $a){
								if($data["target"]->x == $a["x"]and $data["target"]->y === $a["y"]and $data["target"]->z == $a["z"]){
									unset($this->arrival[$key]);
									break;
								}
							}
						}
					}
					foreach($this->departure as $key => $value){
						if($value["x"] == $data["target"]->x and $value["y"] == $data["target"]->y and $value["z"] == $data["target"]->z and $data["target"]->level->getName() == $value["level"]){
							if($this->api->ban->isOp($data["player"]->username) == false){
								$output .= "You don't have permission to destroy airport";
								return false;
							}
							unset($this->departure[$key]);
							return true;
						}
					}
				}
				break;
			}
			foreach($this->departure as $d){
				if($data["target"]->x == $d["x"]and $data["target"]->y == $d["y"]and $data["target"]->z == $d["z"]and $data["target"]->level->getName() == $d["level"]){

					foreach($this->arrival as $v){
						if($d["arrival"] !== $v["name"]){
							continue;
						}
						$world = $this->api->level->get($v["name"]);
						if(!$world instanceof Level){
							$data["player"]->sendChat("Your destination world may be corrupted");
							break 2;
						}
						$can = $this->api->economy->useMoney($player, $d["cost"]);
						if($can !== false){
							$data["player"]->teleport(new Position((int) $v["x"], (int)$v["y"], (int)$v["z"], $world));
							$player->sendChat("Thank you for flying with EA International. We arrived at ".$world->getName()." international airport");
						}else{
							$player->sendChat("You don't have money");
						}
						break 2;
					}
				}
			}
			break;
		case "server.close":
			$this->arrivaldata->setAll($this->arrival);
			$this->departuredata->setAll($this->departure);
			$this->arrivaldata->save();
			$this->departuredata->save();
			break;
		}
	}
	
	public function createArrival($data){
		$this->arrival[] = $data;
	}
	
	public function createDeparture($data){
		$this->departure[] = $data;
	}
}
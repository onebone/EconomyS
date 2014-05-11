<?php
/*
__PocketMine Plugin__
name=EconomySell
description=Plugin of sell center supports EconomyAPI
version=1.1.15
author=onebone
class=EconomySell
apiversion=12,13
*/
/*

CHANGE LOG

V1.0.0 : BETA RELEASE

V 1.0.1 : MINOR BUG FIX

V 1.0.2 : BUG FIX

V 1.0.3 : KOREAN NOW AVALIBLE

V 1.0.4 : KOREAN INVALID ERROR FIX

V 1.0.5 : AMOUNT LOAD BUG FIX

V 1.0.6 : SELL BUG FIX

V 1.0.7 : Added something

V1.1.0 : Item name & item code support

V1.1.1 : Added API, function editSell()

V1.1.2 : Fixed the bug about block place/break not avaliable

V1.1.3 : Fixed bug about cannot break sign

V1.1.4 : Texts changes immediately

V1.1.5 : Now works at DroidPocketMine

V1.1.6 : You have to tap twice to sell item.

V1.1.7 : Minor bug fix

V1.1.8 : Added creating handler

V1.1.9 : Compatible with API 11

V1.1.10 : Compatible with PocketMoney (Configurable)

V1.1.11 : Added item names for 0.8.0

V1.1.12 : Fixed the bug about items.properties

V1.1.13 : 
- Fixed major bugs
- Compatible with API 12 (Amai Beetroot)

V1.1.14 : Added the configuration about frequent saving

V1.1.15 : Some fix of data file parsing

*/

class EconomySell implements Plugin {
	private $api, $items, $tap;
	public $sell;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->sell = array();
		$this->tap = array();
	}

	public function init(){
		$this->createConfig();
		foreach($this->api->plugin->getList() as $p){
			if($p["name"] == "EconomyAPI" and $this->config["compatible-to-economyapi"] or $p["name"] == "PocketMoney" and !$this->config["compatible-to-economyapi"]){
				$exist = true;
				break;
			}

		}
		if(!isset($exist)){
			console("[ERROR] ".($this->config["compatible-to-economyapi"] ? "EconomyAPI" : "PocketMoney")." does not exist");
			$this->api->console->defaultCommands("stop", array(), "plugin", false);
			return;
		}

		$this->loadItems();
		$path = $this->api->plugin->configPath($this);
		EconomySellAPI::set($this);
		$this->api->event("server.close", array($this, "handler"));
		//  $this->api->addHandler("player.block.break", array($this, "handler"));
		$this->api->addHandler("player.block.touch", array($this, "handler"));
		$this->api->event("tile.update", array($this, "handler"));
		$this->centers = new Config($path."SellCenter.yml", CONFIG_YAML);
		$centerd = $this->api->plugin->readYAML($path."SellCenter.yml");
		if(is_array($centerd)){
			foreach($centerd as $c){
				$this->sell[] = array("x" => $c["x"], "y" => $c["y"], "z" => $c["z"], "item" => $c["item"], "amount" => $c["amount"], "cost" => $c["cost"], "level" => $c["level"], "meta" => $c["meta"]);
			}
		}
		$this->api->economy->EconomySRegister("EconomySell");
	}

	public function __destruct(){}
	
	public function createConfig(){
		$this->config = $this->api->plugin->readYAML($this->api->plugin->createConfig($this, array(
			"compatible-to-economyapi" => true,
			"frequent-save" => false
		))."config.yml");
	}
	
	public function editSell($data){
		foreach($this->sell as $k => $s){
			$level = $this->api->level->get($data["level"]);
			if($level == false) 
				return false;
			$t = $this->api->tile->get(new Position($data["x"], $data["y"], $data["z"], $level));
			if($t !== false and $s["x"] == $data["x"]and $data["y"] == $s["y"]and $s["z"] == $data["z"]and $data["level"] == $s["level"]){
				$this->sell[$k] = array("x" => $data["x"], "y" => $data["y"], "z" => $data["z"], "level" => $data["level"], "cost" => $data["cost"], "amount" => $data["amount"], "item" => $s["item"], "meta" => $s["meta"],);
				$t->setText($t->data["Text1"], $data["cost"]."$", $t->data["Text3"], $t->data["Text4"]);
				if($this->config["frequent-save"]){
					$this->centers->setAll($this->sell);
					$this->centers->save();
				}
				return true;
			}
		}
		return false;
	}
	public function handler( &$data, $event){
		$output = "";
		switch ($event){
		case "tile.update":
			if($data->class === TILE_SIGN){
				if($data->data["Text1"] == "sell" or $data->data["Text1"] == "노점상"){
					$lang = $data->data["Text1"] == "sell" ? "english" : "korean";
					$player = $this->api->player->get($data->data["creator"], false);
					if($this->api->ban->isOp($this->api->player->get($data->data["creator"], false)->username) == false){
						if($lang == "english"){
							$this->api->player->get($data->data["creator"], false)->sendChat("You don't have permission to open sell center");
						}else{
							$this->api->player->get($data->data["creator"], false)->sendChat("당신은 노점상을 생성할 권한이 없습니다");
						}
						break;
					}
					if($data->data["Text2"] == "" or $data->data["Text3"] == "" or $data->data["Text4"] == ""){
						if($lang == "english"){
							$output .= "Incorrect sell center data";

						}else{
							$output .= "노점상의 데이터가 올바르지 않습니다.";
						}
						$player->sendChat($output);
						break;
					}else{
						if(strpos($data->data["Text3"], ":") !== false){
							$e = explode(":", $data->data["Text3"]);
						}else{
							$e = explode(":", $data->data["Text3"]);
							$e[1] = isset($e[1]) ? $e[1] : 0;
							if(is_numeric($e[0]) and is_numeric($e[1])){
								$e[0] = $data->data["Text3"];
								$e[1] = 0;
							}else{
								$e = explode(":", $data->data["Text3"]);
								$e[1] = isset($e[1]) ? $e[1] : 0;
							}
						}
						if(is_numeric($e[0]) and is_numeric($e[1])){
							$name = $this->getItem($e[0].":".$e[1]);
							if($name == false){
								$player->sendChat($lang == "english" ? "Item ".$data->data["Text3"]." does not support at EconomySell" : "아이템 ".$data->data["Text3"]."는 EconomySell 에서 지원하지 않습니다");
								break;
							}
						}else{
							$id = $this->getItem($data->data["Text3"]);
							if($id == false){
								$player->sendChat($lang == "english" ? "Item ".$data->data["Text3"]." does not support at EconomySell" : "아이템 ".$data->data["Text3"]."는 EconomySell 에서 지원하지 않습니다");
								break;
							}
							$e = explode(":", $id);
							$e[1] = isset($e[1]) ? $e[1] : 0;
						}
						if($this->api->dhandle("economysell.sellcenter.create", array("x" => $data->x, "y" => $data->y, "z" => $data->z, "item" => $e[0], "meta" => $e[1], "cost" => str_replace("$", "", $data->data["Text2"]), "amount" => $data->data["Text4"], "level" => $data->level->getName())) !== false){
							$this->createSellCenter(array("meta" => $e[1], "x" => $data->data["x"], "y" => $data->data["y"], "z" => $data->data["z"], "item" => $e[0], "cost" => str_replace("$", "", $data->data["Text2"]), "amount" => $data->data["Text4"], "level" => $data->level->getName()));
							if($lang == "english"){
								$data->setText("[SELL]", $data->data["Text2"]."$", isset($name) ? $name : $data->data["Text3"], "Amount : ".$data->data["Text4"]);
								$output .= "Sell center created";
							}else{
								$data->setText("[노점상]", $data->data["Text2"]."$", isset($name) ? $name : $data->data["Text3"], "수량 : ".$data->data["Text4"]);
								$output .= "노점상이 생성되었습니다.";
							}
						}else{
							$output .= "Failed to create sell center due to unknown error.";
						}
					}
					$player->sendChat($output);
				}
			}

			break;
		case "player.block.touch":
			if($data["type"] == "break"){
				if($data["target"]->getID() == 323 or $data["target"]->getID() === 68 or $data["target"]->getID() == 63){
					if($this->sell == null){
						break;
					}
					foreach($this->sell as $s){
						if($s["x"] == $data["target"]->x and $s["y"] == $data["target"]->y and $data["target"]->z == $s["z"]){
							foreach($this->sell as $key => $value){
								if($value["x"] == $data["target"]->x and $value["y"] == $data["target"]->y and $value["z"] == $data["target"]->z and $value["level"] == $data["target"]->level->getName()){
									if($this->api->ban->isOp($data["player"]) == false){
										$data["player"]->close("tried to destroy sell center");
										return false;

									}
									unset($this->sell[$key]);
									if($this->config["frequent-save"]){
										$this->centers->setAll($this->sell);
										$this->centers->save();
									}
								}
							}
						}
					}
				}
				break; /// here ///
			}
			if($data["target"]->getID() == 323 or $data["target"]->getID() == 68 or $data["target"]->getID() == 63){
				if(!is_array($this->sell)){
					break;
				}
				foreach($this->sell as $s){
					if($s["x"] == $data["target"]->x and $s["y"] == $data["target"]->y and $data["target"]->z == $s["z"]and $s["level"] == $data["target"]->level->getName()){
						$can = false;
						if($data["player"]->gamemode == CREATIVE){
							$data["player"]->sendChat("You are in creative mode");
							return false;
						}
						if(!isset($this->tap[$data["player"]->username])){
							$this->tap[$data["player"]->username] = array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"]);
							$this->api->schedule(20, array($this, "removeTap"), $data["player"]->username);
							$data["player"]->sendChat("Are you sure to sell this? Tap again to confirm.");
							break;
						}
						if(!($s["x"] == $this->tap[$data["player"]->username]["x"]and $s["y"] == $this->tap[$data["player"]->username]["y"]and $s["z"] == $this->tap[$data["player"]->username]["z"])){
							$data["player"]->sendChat("Are you sure to sell this? Tap again to confirm.");
							$this->tap[$data["player"]->username] = array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"]);
							$this->api->schedule(20, array($this, "removeTap"), $data["player"]->username);
							$this->cancel[$data["player"]->username] = true;
							break;
						}
						$cnnt = 0;
						$ss = null;
						foreach($data["player"]->inventory as $slot => $item){
							if($s["item"] == $item->getID() and $item->getMetadata() == $s["meta"]){
								$iii = $item;
								$cnnt += $item->count;
								if($cnnt >= $s["amount"]){
									$can = true;
								}else{
									//  $cnnt += $item->count;
									$ss[] = array("sl" => $slot, "count" => $item->count);
								}
							}
						}
						if($can == false){
							$data["player"]->sendChat("You don't have the item to sell");
							return false;
						}
						if($ss !== null){
							foreach($ss as $slots){
								extract($slots);
								$s["amount"] -= $count;
								$data["player"]->setSlot($sl, BlockAPI::getItem(AIR, 0, 0));
							}
						}
						$data["player"]->removeItem($iii->getID(), $iii->getMetadata(), $s["amount"]);
						if($this->config["compatible-to-economyapi"]){
							$this->api->economy->takeMoney($data["player"], $s["cost"]);
						}else{
							$this->api->dhandle("money.handle", array(
								"username" => $data["player"]->username,
								"method" => "grant",
								"amount" => $s["cost"],
								"issuer" => "EconomySell" // Disappeared?
							));
						}
						unset($this->tap[$data["player"]->username]);
						$data["player"]->sendChat("You have been sold your item");
						return false;
					}
				}
			}
			break;
		case "server.close":
			$this->centers->setAll($this->sell);
			$this->centers->save();
			break;
		}
	}
	public function removeTap($username){
		if(isset($this->cancel[$username])){
			unset($this->cancel[$username]);
			return false;
		}
		if(isset($this->tap[$username])) 
			unset($this->tap[$username]);
		}
	public function createSellCenter($c){
		$this->sell[] = array("x" => $c["x"], "y" => $c["y"], "z" => $c["z"], "item" => $c["item"], "cost" => $c["cost"], "amount" => $c["amount"], "level" => $c["level"], "meta" => $c["meta"]);
		if($this->config["frequent-save"]){
			$this->centers->setAll($this->sell);
			$this->centers->save();
		}
	}
	public function getItem($item){ // gets ItemID and ItemName
		$e = explode(":", $item);
		if(count($e) > 1){
			if(is_numeric($e[0])){
				foreach($this->items as $k => $i){
					$item = explode(":", $i);
					$e[1] = isset($e[1]) ? $e[1] : 0;
					$item[1] = isset($item[1]) ? $item[1] : 0;
					if($e[0] == $item[0]and $e[1] == $item[1]){
						return $k;
					}
				}
				return false;
			}
		}else{
			$item = strtolower($item);
			if(isset($this->items[$item])){
				return $this->items[$item];
			}else{
				return false;
			}
		}
	}
	public function loadItems(){
		$items = array();
		if(!is_file(DATA_PATH."plugins/EconomySell/items.properties")){
		$items = new Config(DATA_PATH."plugins/EconomySell/items.properties", CONFIG_PROPERTIES, array(
		"air" => 0,
		"stone" => 1,
		"grassblock" => 2,
		"dirt" => 3,
		"cobblestone" => 4,
		"woodenplank" => 5,
		"treesapling" => 6,
		"firsapling" => "6:1",
		"birchsapling" => "6:2",
		"bedrock" => 7,
		"water" => 8,
		"stationarywater" => 9,
		"lava" => 10,
		"stationarylava" => 11,
		"sand" => 12,
		"gravel" => 13,
		"goldore" => 14,
		"ironore" => 15,
		"coalore" => 16,
		"tree" => 17,
		"oakwood" => "17:1",
		"birchwood" => "17:2",
		"treeleaf" => "18",
		"oaktreeleaf" => "18:1",
		"birchtreeleaf" => "18:2",
		"sponge" => 19,
		"glass" => 20,
		"lapisore" => 21,
		"lapisblock" => 22,
		"sandstone" => 24,
		"sandstone2" => "24:1",
		"sandstone3" => "24:2",
		"bed" => 26,
		"poweredrail" => 27,
		"cobweb" => 30,
		"bush" => 31,
		"whitewool" => 35,
		"orangewool" => "35:1",
		"magentawool" => "35:2",
		"skywool" => "35:3",
		"yellowwool" => "35:4",
		"greenwool" => "35:5",
		"pinkwool" => "35:6",
		"greywool" => "35:7",
		"greywool2" => "35:8",
		"bluishwool" => "35:9",
		"purplewool" => "35:10",
		"bluewool" => "35:11",
		"brownwool" => "35:12",
		"greenwool2" => "35:13",
		"redwool" => "35:14",
		"blackwool" => "35:15",
		"yellowflower" => 37,
		"blueflower" => 38,
		"brownmushroom" => 39,
		"redmushroom" => 40,
		"goldblock" => 41,
		"ironblock" => 42,
		"stonefoothold" => 43,
		"sandfoothold" => "43:1",
		"woodfoothold" => "43:2",
		"cobblefoothold" => "43:3",
		"brickfoothold" => "43:4",
		"stonefoothold2" => "43:6",
		"halfstone" => 44,
		"halfsand" => "44:1",
		"halfwood" => "44:2",
		"halfcobble" => "44:3",
		"halfbrick" => "44:4",
		"halfstone2" => "44:6",
		"brick" => 45,
		"tnt" => 46,
		"bookshelf" => 47,
		"mossstone" => 48,
		"obsidian" => 49,
		"torch" => 50,
		"fire" => 51,
		"woodstair" => 53,
		"chest" => 54,
		"diamondore" => 56,
		"diamondblock" => 57,
		"craftingtable" => 58,
		"crop" => 59,
		"farmland" => 60,
		"furnace" => 61,
		"signblock" => 63,
		"burningfurnace" => 62,
		"woodendoor" => 64,
		"ladder" => 65,
		"cobblestair" => 67,
		"wallsign" => 68,
		"irondoor" => 71,
		"redstoneore" => 73,
		"glowredstone" => 74,
		"snow" => 78,
		"ice" => 79,
		"snowblock" => 80,
		"cactus" => 81,
		"clayblock" => 82,
		"sugarcane" => 83,
		"fence" => 85,
		"pumpkin" => 86,
		"netherrack" => 87,
		"glowingstone" => 89,
		"jack-o-lanton" => 91,
		"cake" => 92,
		"invisiblebedrock" => 95,
		"trapdoor" => 96,
		"stonebrick" => 98,
		"mossbrick" => "98:1",
		"crackedbrick" => "98:2",
		"ironbars" => 101,
		"flatglass" => 102,
		"watermelon" => 103,
		"fencegate" => 107,
		"brickstair" => 108,
		"stonestair" => 109,
		"netherbrick" => 112,
		"netherbrickstair" => 114,
		"sandstair" => 128,
		"growingcarrot" => 141,
		"growingpotato" => 142,
		"quartzblock" => 155,
		"softquartz" => "155:1",
		"pilliarquartz" => "155:2",
		"quartzstair" => 156,
		"haybale" => 170,
		"carpet" => 171,
		"coalblock" => 173,
		"beetroot" => 244,
		"stonecutter" => 245,
		"glowingobsidian" => 246,
		"nethercore" => 247,
		"updateblock1" => 248,
		"updateblock2" => 249,
		"errorgrass" => 253,
		"errorleaves" => 254,
		"errorstone" => 255,
		"ironshovel" => 256,
		"ironpickaxe" => 257,
		"ironaxe" => 258,
		"flintandsteel" => 259,
		"apple" => 260,
		"bow" => 261,
		"arrow" => 262,
		"coal" => 263,
		"charcoal" => "263:1",
		"diamond" => 264,
		"ironingot" => 265,
		"goldingot"=> 266,
		"ironsword" => 267,
		"woodsword" => 268,
		"woodshovel" => 269,
		"woodpickaxe" => 270,
		"woodaxe" => 271,
		"stonesword" => 272,
		"stoneshovel" => 273,
		"stonepickaxe" => 274,
		"stoneaxe" => 275,
		"diamondsword" => 276,
		"diamondshovel" => 277,
		"diamondpickaxe" => 278,
		"diamondaxe" => 279,
		"stick" => 280,
		"bowl" => 281,
		"mushroomstew" => 282,
		"goldsword" => 283,
		"goldshovel" => 284,
		"goldpickaxe" => 285,
		"goldaxe" => 286,
		"web" => 287,
		"feather" => 288,
		"gunpowder" => 289,
		"woodhoe" => 290,
		"stonehoe" => 291,
		"ironhoe" => 292,
		"diamondhoe" => 293,
		"goldhoe" => 294,
		"seed" => 295,
		"wheat" => 296,
		"bread" => 297,
		"leatherhat" => 298,
		"leatherarmor" => 299,
		"leatherpants" => 300,
		"leatherboots" => 301,
		"chairhat" => 302,
		"chainchestplate" => 303,
		"chainlegging" => 304,
		"chainboots" => 305,
		"ironhelmet" => 306,
		"ironchestplate" => 307,
		"ironlegging"=> 308,
		"ironboots" => 309,
		"diamondhelmet" => 310,
		"diamondchestplate" => 311,
		"diamondlegging" => 312,
		"diamondboots" => 313,
		"goldhelmet" => 314,
		"goldchestplate" => 315,
		"goldlegging" => 316,
		"goldboots" => 317,
		"flint" => 318,
		"rawpork" => 319,
		"pork" => 320,
		"paint" => 321,
		"sign" => 323,
		"door" => 324,
		"bucket" => 325,
		"waterbucket" => 326,
		"irondoor" => 330,
		"redstone" => 331,
		"snowball" => 332,
		"leather" => 334,
		"claybrick" => 336,
		"clay" => 337,
		"sugarcane" => 338,
		"paper" => 339,
		"book" => 340,
		"slimeball" => 341,
		"egg" => 344,
		"compass" => 345,
		"clock" => 347,
		"glowstone" => 348,
		"ink" => 351,
		"redrose" => "351:1",
		"greencactus" => "351:2",
		"cocoabean" => "351:3",
		"lapislazuli" => "351:4",
		"cotton" => "351:5",
		"bluish" => "351:6",
		"lightgrey" => "351:7",
		"grey" => "351:8",
		"pink" => "351:9",
		"lightgreen" => "351:10",
		"yellow" => "351:11",
		"sky" => "351:12",
		"magenta"=> "351:13",
		"orange" => "351:14",
		"bonemeal" => "351:15",
		"bone" => 352,
		"sugar" => 353,
		"cake" => 354,
		"bed" => 355,
		"scissors" => 259,
		"melon" => 360,
		"pumpkinseed" => 361,
		"melonseed" => 362,
		"rawbeef" => 363,
		"stake" => 364,
		"rawchicken" => 365,
		"chicken" => 366,
		"carrot" => 391,
		"potato" => 392,
		"cookedpotato" => 393,
		"pumpkinpie" => 400,
		"netherbrick" => 405,
		"hellquartz" => 406,
		"camera" => 456,
		"beetroot" => 457,
		"beetrootseed" => 458,
		"beetrootsoup" => 459
		));
	}else{
		$items = new Config(DATA_PATH."plugins/EconomyShop/items.properties", CONFIG_PROPERTIES);
	}
	$this->items = array_change_key_case($items->getAll(), CASE_LOWER);
	}
}
class EconomySellAPI { // Use this API to control EconomySell!
	public static $d;
	public static function set(EconomySell $e){
		if(EconomySellAPI::$d instanceof EconomySell) 
			return false;
		EconomySellAPI::$d = $e;
		return true;
	}
	public static function getSells(){
		return EconomySellAPI::$d->sell;
	}
	public static function editSell($data){
		return EconomySellAPI::$d->editSell($data);
	}
}
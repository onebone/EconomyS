<?php
/*
__PocketMine Plugin__
name=EconomySell
description=Plugin of sell center supports EconomyAPI
version=1.2.0
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

V1.2.0 : Rewrote EconomySell database

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
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] Cannot find EconomyAPI");
			$this->api->console->defaultCommands("stop", array(), "plugin", false);
			return;
		}
		
		$centers = new Config(DATA_PATH."plugins/EconomySell/Sell.yml", CONFIG_YAML);
		$this->sell = $centers->getAll();
		
		$this->loadItems();
		$this->convertData();
		
		EconomySellAPI::set($this);
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.block.touch", array($this, "handler"));
		$this->api->event("tile.update", array($this, "handler"));
		$this->api->economy->EconomySRegister("EconomySell");
	}
	
	public function __destruct(){}
	
	private function convertData(){
		if(is_file(DATA_PATH."plugins/EconomySell/SellCenter.yml")){
			$cnt = 0;
			foreach($this->api->plugin->readYAML(DATA_PATH."plugins/EconomySell/SellCenter.yml") as $sell){
				$this->sell[$sell["x"].":".$sell["y"].":".$sell["z"].":".$sell["level"]] = $sell;
				++$cnt;
			}
			var_dump($this->sell);
			@unlink(DATA_PATH."plugins/EconomySell/SellCenter.yml");
			console(FORMAT_AQUA."[EconomySell] $cnt of sell center data(m) has been converted into new database");
		}
	}
	
	private function createConfig(){
		$this->config = $this->api->plugin->readYAML($this->api->plugin->createConfig($this, array(
			"compatible-to-economyapi" => true,
			"frequent-save" => false
		))."config.yml");
		
		$this->lang = new Config(DATA_PATH."plugins/EconomySell/language.properties", CONFIG_PROPERTIES, array(
			"wrong-format" => "Please write your sign with right format",
			"item-not-support" => "Item %1 is not supported on EconomySell",
			"no-permission-create" => "You don't have permission to create sell center",
			"sell-created" => "Sell center has been created (%1:%2 = $%3)",
			"removed-sell" => "Sell center has been removed",
			"creative-mode" => "You are in creative mode",
			"no-permission-break" => "You don't have permission to break sell center",
			"tap-again" => "Are you sure to sell %1 ($%2)? Tap again to confirm",
			"no-item" => "You have no item to sell",
			"sold-item" => "Has been sold %1 of %2 for $%3"
		));
		
		$this->sellSign = new Config(DATA_PATH."plugins/EconomySell/SellSign.yml", CONFIG_YAML, array(
			"sell" => array(
				"[SELL]",
				"$%1",
				"%2",
				"Amount : %3"
			)
		));
	}
	
	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3"), array($val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "There's no message named \"$key\"";
	}
	
	public function editSell($data){
		if(isset($this->sell[$data["x"].":".$data["y"].":".$data["z"].":".$data["level"]])){
			$level = $this->api->level->get($data["level"]);
			if(!$level instanceof Level){
				return false;
			}
			$t = $this->api->tile->get(new Position($data["x"], $data["y"], $data["z"], $level));
			if($t !== false){
				$t->setText($t->data["Text1"], "$".$data["cost"], $t->data["Text3"], $data["Text4"]);
			}
			$this->sell[$data["x"].":".$data["y"].":".$data["z"].":".$data["level"]] = array(
				"x" => $data["x"],
				"y" => $data["y"],
				"z" => $data["z"],
				"level" => $data["level"],
				"item" => $data["item"],
				"meta" => $data["meta"],
				"cost" => $data["cost"],
				"amount" => $data["amount"]
			);
			return true;
		}
		return false;
	}
	
	public function checkTag($line1){
		foreach($this->sellSign->getAll() as $tag => $val){
			if($tag == $line1){
				return $val;
			}
		}
		return false;
	}
	
	public function handler($data, $event){
		$output = "";
		switch ($event){
		case "tile.update":
			if($data->class === TILE_SIGN){
				if(($val = $this->checkTag($data->data["Text1"])) !== false){
					$player = $this->api->player->get($data->data["creator"], false);
					if(!$this->api->ban->isOp($data->data["creator"])){
						$player->sendChat($this->getMessage("no-permission-create"));
						return;
					}
					if(!is_numeric($data->data["Text2"]) or !is_numeric($data->data["Text4"])){
						$player->sendChat($this->getMessage("wrong-format"));
						return;
					}
					
					// Item identify
					$item = $this->getItem($data->data["Text3"]);
					if($item === false){
						$player->sendChat($this->getMessage("item-not-support", array($data->data["Text3"], "", "")));
						return;
					}
					if($item[1] === false){ // Item name found
						$id = explode(":", strtolower($data->data["Text3"]));
						$data->data["Text3"] = $item[0];
					}else{
						$tmp = $this->getItem(strtolower($data->data["Text3"]));
						$id = explode(":", $tmp[0]);
					}
					$id[0] = (int)$id[0];
					if(!isset($id[1])){
						$id[1] = 0;
					}
					// Item identify end
					
					$this->sell[$data->x.":".$data->y.":".$data->z.":".$data->level->getName()] = array(
						"x" => $data->x,
						"y" => $data->y,
						"z" => $data->z,
						"level" => $data->level->getName(),
						"cost" => (int) $data->data["Text2"],
						"item" => (int) $id[0],
						"meta" => (int) $id[1],
						"amount" => (int) $data->data["Text4"]
					);
				
					$player->sendChat($this->getMessage("sell-created", array($id[0], $id[1], $data->data["Text2"])));
					
					$data->data["Text1"] = $val[0];
					$data->data["Text2"] = str_replace("%1", $data->data["Text2"], $val[1]);
					$data->data["Text3"] = str_replace("%2", $data->data["Text3"], $val[2]);
					$data->data["Text4"] = str_replace("%3", $data->data["Text4"], $val[3]);
					
					$this->api->tile->spawnToAll($data);
				}
			}

			break;
		case "player.block.touch":
			if($data["type"] === "break"){
				if($data["target"]->getID() === 323 or $data["target"]->getID() === 68 or $data["target"]->getID() === 63){
					if(isset($this->sell[$data["target"]->x.":".$data["target"]->y.":".$data["target"]->z.":".$data["target"]->level->getName()])){
						if($this->api->ban->isOp($data["player"]->iusername)){
							unset($this->sell[$data["target"]->x.":".$data["target"]->y.":".$data["target"]->z.":".$data["target"]->level->getName()]);
							$data["player"]->sendChat($this->getMessage("removed-sell"));
							return;
						}else{
							$data["player"]->sendChat($this->getMessage("no-permission-break"));
							return false;
						}
					}
				}
			}
			
			if($data["target"]->getID() == 323 or $data["target"]->getID() == 68 or $data["target"]->getID() == 63){
				if(isset($this->sell[$data["target"]->x.":".$data["target"]->y.":".$data["target"]->z.":".$data["target"]->level->getName()])){
					if($data["player"]->gamemode === CREATIVE){
						$data["player"]->sendChat($this->getMessage("creative-mode"));
						return false;
					}
					$sellInfo = $this->sell[$data["target"]->x.":".$data["target"]->y.":".$data["target"]->z.":".$data["target"]->level->getName()];
					if(isset($this->tap[$data["player"]->iusername])){
						if((time() - $this->tap[$data["player"]->iusername][1]) > 2){
							$this->tap[$data["player"]->iusername] = array($data["target"]->x.":".$data["target"]->y.":".$data["target"]->z, time());
							$data["player"]->sendChat($this->getMessage("tap-again", array($sellInfo["item"].":".$sellInfo["meta"], $sellInfo["cost"], "%3")));
							break;
						}
						$cnt = 0;
						foreach($data["player"]->inventory as $slot => $item){
							if($item->getID() === $sellInfo["item"] and $item->getMetadata() === $sellInfo["meta"]){
								$cnt += $item->count;
								if($cnt >= $sellInfo["amount"]) break;
							}
						}
						
						if($cnt >= $sellInfo["amount"]){
							$data["player"]->removeItem($sellInfo["item"], $sellInfo["meta"], $sellInfo["amount"]);
							$this->api->economy->takeMoney($data["player"], $sellInfo["cost"]);
							$data["player"]->sendChat($this->getMessage("sold-item", array($sellInfo["amount"], $sellInfo["item"].":".$sellInfo["meta"], $sellInfo["cost"])));
							unset($this->tap[$data["player"]->iusername]);
						}else{
							unset($this->tap[$data["player"]->iusername]);
							$data["player"]->sendChat($this->getMessage("no-item"));
						}
						return false;
					}else{
						$this->tap[$data["player"]->iusername] = array($data["target"]->x.":".$data["target"]->y.":".$data["target"]->z, time());
						$data["player"]->sendChat($this->getMessage("tap-again", array($sellInfo["item"].":".$sellInfo["meta"], $sellInfo["cost"], "%3")));
						return false;
					}
				}
			}
			break;
		case "server.close":
			$sellCfg = new Config(DATA_PATH."plugins/EconomySell/Sell.yml", CONFIG_YAML);
			$sellCfg->setAll($this->sell);
			$sellCfg->save();
			break;
		}
	}
		
	public function createSellCenter($c){
		$this->sell[] = array("x" => $c["x"], "y" => $c["y"], "z" => $c["z"], "item" => $c["item"], "cost" => $c["cost"], "amount" => $c["amount"], "level" => $c["level"], "meta" => $c["meta"]);
		if($this->config["frequent-save"]){
			$this->centers->setAll($this->sell);
			$this->centers->save();
		}
	}
	
	public function getItem($item){ // gets ItemID and ItemName
		$item = strtolower($item);
		$e = explode(":", $item);
		$e[1] = isset($e[1]) ? $e[1] : 0;
		if(array_key_exists($item, $this->items)){
			return array($this->items[$item], true); // Returns Item ID
		}else{
			foreach($this->items as $name => $id){
				$explode = explode(":", $id);
				$explode[1] = isset($explode[1]) ? $explode[1]:0;
				if($explode[0] == $e[0] and $explode[1] == $e[1]){
					return array($name, false);
				}
			}
		}
		return false;
	}
	
	public function loadItems(){
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
		$items = new Config(DATA_PATH."plugins/EconomySell/items.properties", CONFIG_PROPERTIES);
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
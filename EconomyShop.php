<?php

/*
__PocketMine Plugin__
name=EconomyShop
version=1.2.4
author=onebone
apiversion=12,13
class=EconomyShop
*/

/*
CHNAGE LOG
==================
V 1.0.0 : Initial Release

V 1.0.1 : Korean now avaliable

V 1.0.2 : Korean error fix

V 1.0.3 : Added meta

V 1.0.4 : Added something

V1.0.5 : Fixed about the sign has been disappeared

V1.0.6 : Fixed about the block break/place not avaliable

V1.0.7 : Fixed bug which cannot break sign

V1.0.8 : Now texts changes immediately

V1.0.9 : Now works at DroidPocketMine

V1.1.0 : Supports item name

V1.1.1 : To buy, you need to tap twice

V1.1.2 : Minor bug fixed

V1.1.3 : Added creating handler

V1.1.4 : Compatible with API 11

V1.1.5 : Compatible with PocketMoney. (Configurable)

V1.1.6 : Minor bug has fixed

V1.1.7 : Added item names for 0.8.0

V1.1.8 : Fixed the bug about items.properties

V1.1.9 : 
- Fixed major bugs
- Compatible with API 12 (Amai Beetroot)

V1.1.10 : Added the configuration of frequent saving

V1.2.0 : Creates language.properties file

V1.2.1 : Fixed bug

V1.2.2 :
- Hopefully fixed the bug
- Checks data

V1.2.3 : 
- Rewrote codes
- Database is now SQLite3

V1.2.4 : Bug fixed - Item name is not supported correctly

V1.2.5 : Easily access of EconomyShop

*/

class EconomyShop implements Plugin{
	private $api, $shop, $config, $tap, $id, $shopSign;
	private static $obj;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->tap = array();
		$this->id = array();
	}
	
	public function init(){
		@mkdir(DATA_PATH."plugins/EconomyShop/");
		$this->shop = new SQLite3(DATA_PATH."plugins/EconomyShop/Shops.sqlite3");
		$this->shop->exec("CREATE TABLE IF NOT EXISTS shop(
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			x INTEGER NOT NULL,
			y INTEGER NOT NULL,
			z INTEGER NOT NULL,
			level TEXT NOT NULL,
			price NUMERIC NOT NULL,
			item INTEGER NOT NULL,
			meta INTEGER NOT NULL,
			amount INTEGER NOT NULL
		);");
		$this->createConfig();
		$this->loadItems();
		$this->convertData();
		$priority = 5;
		if(is_numeric($this->config->get("handler-priority"))){
			$priority = (int)$this->config->get("handler-priority");
		}
		$priority &= PHP_INT_MAX;
		$this->api->event("tile.update", array($this, "onTileUpdate"), $priority);
		$this->api->addHandler("player.block.touch", array($this, "onTouch"), $priority);
		$this->api->economy->EconomySRegister("EconomyShop");
		self::$obj = $this;
	}
	
	public function __destruct(){}
	
	public static function getInstance(){
		return self::$obj;
	}
	
	private function createConfig(){
		$this->config = new Config(DATA_PATH."plugins/EconomyShop/shop.properties", CONFIG_PROPERTIES, array(
			"handler-priority" => 5,
		));
		
		$this->lang = new Config(DATA_PATH."plugins/EconomyShop/language.properties", CONFIG_PROPERTIES, array(
			"wrong-format" => "Please write your sign with right format",
			"item-not-support" => "Item %1 is not supported on EconomyShop",
			"no-permission-create" => "You don't have permission to create shop",
			"shop-created" => "Shop has been created",
			"removed-shop" => "Shop has been removed",
			"no-permission-break" => "You don't have permission to break shop",
			"tap-again" => "Are you sure to buy %1 ($%2)? Tap again to confirm",
			"no-money" => "You don't have to buy $%1",
			"bought-item" => "Has been bought %1 of %2 for $%3"
		));
		
		$this->shopSign = new Config(DATA_PATH."plugins/EconomyShop/ShopSign.yml", CONFIG_YAML, array(
			"shop" => array(
				"[SHOP]",
				"$%1",
				"%2",
				"Amount : %3"
			)
		));
	}
	
	public function editShop($x, $y, $z, $level, $price, $item, $damage, $amount){
		if(!is_numeric($x) or !is_numeric($y) or !is_numeric($z) or !is_string($level) or !is_numeric($price) or !is_numeric($item) or !is_numeric($damage) or !is_numeric($amount))
			return false;
			
		$info = $this->shop->query("SELECT * FROM shop WHERE x = $x AND y = $y AND z = $z AND level = '$level'")->fetchArray(SQLITE3_ASSOC);
		if(is_bool($info)){
			return false;
		}
		$this->shop->exec("UPDATE shop SET item=$item, meta=$damage, price=$price, amount=$amount WHERE ID = {$info["id"]}");
		return true;
	}
	
	public function getShops(){
		$ret = array();
		$s = $this->shop->query("SELECT * FROM shop");
		while(($r = $s->fetchArray(SQLITE3_ASSOC)) !== false){
			$ret[] = $r;
		}
		return $ret;
	}
	
	private function convertData(){
		if(is_file(DATA_PATH."plugins/EconomyShop/Shops.yml")){
			$cnt = 0;
			$data = $this->api->plugin->readYAML(DATA_PATH."plugins/EconomyShop/Shops.yml");
			foreach($data as $d){
				$this->shop->exec("INSERT INTO shop (x, y, z, level, price, item, meta, amount) VALUES ($d[x], $d[y], $d[z], '$d[level]', $d[price], $d[item], $d[meta], $d[amount]);");
				++$cnt;
			}
			@unlink(DATA_PATH."plugins/EconomyShop/Shops.yml");
			console(FORMAT_AQUA."[EconomyShop] Converted $cnt of shops into new database");
		}
	}
	
	public function tagExists($tag){
		foreach($this->shopSign->getAll() as $key => $val){
			if($tag == $key){
				return true;
			}
		}
		return false;
	}
	
	public function getData($tag){
		foreach($this->shopSign->getAll() as $key => $val){
			if($tag == $key){
				return $val;
			}
		}
		return false;
	}
	
	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3"), array($val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "There are no message which has key \"$key\"";
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
	
	public function onTileUpdate($data){
		if($data->class === TILE_SIGN){
			$result = $this->tagExists($data->data["Text1"]);
			if($result){
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
				
				$this->shop->exec("INSERT INTO shop (x, y, z, level, price, item, meta, amount) VALUES ({$data->x}, {$data->y}, {$data->z}, '{$data->level->getName()}', {$data->data["Text2"]}, $id[0], $id[1], {$data->data["Text4"]});");
				
				$d = $this->getData($data->data["Text1"]);
				$data->data["Text1"] = $d[0];
				$data->data["Text2"] = str_replace("%1", $data->data["Text2"], $d[1]);
				$data->data["Text3"] = str_replace("%2", $data->data["Text3"], $d[2]);
				$data->data["Text4"] = str_replace("%3", $data->data["Text4"], $d[3]);
				
				$this->api->tile->spawnToAll($data);
				$player->sendChat($this->getMessage("shop-created"));
			}
		}
	}
	
	public function onTouch($data){
	//	$signArr = array(63, 68, 323);
		$id = $data["target"]->getID();
	//	if(in_array($data["target"]->getID(), $signArr)){
		if($id === 63 or $id === 68 or $id === 323){
			$info = $this->shop->query("SELECT * FROM shop WHERE x = {$data["target"]->x} AND y = {$data["target"]->y} AND z = {$data["target"]->z} AND level = '{$data["target"]->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
			if(!is_bool($info)){
				if($data["type"] === "break"){
					if($this->api->ban->isOp($data["player"]->iusername)){
						$this->shop->exec("DELETE FROM shop WHERE id = $info[id]");
						$data["player"]->sendChat($this->getMessage("removed-shop"));
					}else{
						$data["player"]->sendChat($this->getMessage("no-permission-break"));
						return false;
					}
					return;
				}
				if(!in_array($data["player"]->iusername, $this->tap)){
					$id = $this->getRandIdentifier();
					$this->tap[$id] = $data["player"]->iusername;
					$this->api->schedule(25, array($this, "removeTapSchedule"), $id);
					$data["player"]->sendChat($this->getMessage("tap-again", array($info["item"].":".$info["meta"], $info["price"], "")));
					return false;
				}else{
					if($this->api->economy->useMoney($data["player"], $info["price"])){
						$data["player"]->addItem($info["item"], $info["meta"], $info["amount"]);
						$data["player"]->sendChat($this->getMessage("bought-item", array($info["amount"], $info["item"].":".$info["meta"], $info["price"])));
						$this->removeTapSchedule(array_search($data["player"]->iusername, $this->tap));
						return false;
					}else{
						$data["player"]->sendChat($this->getMessage("no-money", array($info["price"], $info["item"].":".$info["meta"], "")));
						return false;
					}
				}
			}
		}
	}
	
	public function removeTapSchedule($id){
		if(isset($this->tap[$id])){
			$this->tap[$id] = null;
			unset($this->tap[$id]);
		}
	}
	
	public function getRandIdentifier($len = 20){
		$i = "";
		for($a = 0; $a < $len; $a++){
			$rand = rand(0, 15);
			$i .= dechex($rand);
		}
		return $i;
	}
	
	/*
	$this->api->schedule(15, function($data){
		
	}, array());
	*/ // I just wanted to use anonymous function!!
	
	public function loadItems(){ // I managed to align this all items list :P
		$items = array();
		if(!is_file(DATA_PATH."plugins/EconomyShop/items.properties")){
			$this->items = array_change_key_case((new Config(DATA_PATH."plugins/EconomyShop/items.properties", CONFIG_PROPERTIES, array(
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
				"sand" 	=> 12,
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
				"chest"	 => 54,
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
				"nether	core" => 247,
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
			)))->getAll(), CASE_LOWER);
		}else{
			$this->items = array_change_key_case(((new Config(DATA_PATH."plugins/EconomyShop/items.properties", CONFIG_PROPERTIES))->getAll()), CASE_LOWER);
		}
	}
}
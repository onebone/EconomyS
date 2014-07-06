<?php
/*
__PocketMine Plugin__
name=EconomyLand
version=1.4.6
author=onebone
apiversion=12,13
class=EconomyLand
*/
/*

=====CHANGE LOG======
V1.0.0 : First release

V1.0.1 : Multi World support

V1.0.2 : Bug fix

V1.1.0 :
- Multi World bug fix
- Rotation command add
- Added handler
- Added EconomyLandAPI
- More

V1.1.1 : OPs can edit the land

V1.2.0 :
- Changed the selection of land( Sign → Command )
- Reduced saving data

V1.2.1 : Fixed about space calculating error

V1.2.2 : Fixed small bug and edited function editLand()

V1.2.3 : Added configuration to add Korean command

V1.2.4 : Now works at DroidPocketMine

V1.2.5 : Added "move" sub command for command "/land"

V1.3.0 : Added invite system with command /land <invite | invitee>

V1.3.1 :
- Increased handler priority
- Error fix

V1.3.2 : Added command '/land give'

V1.3.3 : Compatible with API 11

V1.3.4 : Fixed editLand() to compatible with current version

V1.3.5 :
- Fixed the bug that cannot remove invitee
- Changed method of buying land

V1.3.6 : Fixed major bug

V1.3.7 : Compatible with API 12 (Amai Beetroot)

V1.3.8 : Edited the /land here to show land number

V1.3.9 : Fixed some invitee issue

V1.4.0 : 
- Added handler priority configuration
- Added white-protection configuration
- Language preferences file

V1.4.1 : 
- Fixed minor bug
- white-protection is now available to set the specified world

V1.4.2 : 
- Available to set white-world-protection as array
- Can't pick up items in others' land
- Changed database to SQLite3
- Now available to limit player's land count

V1.4.3 : Fixed bug

V1.4.4 : Fixed bug about server closing

V1.4.5 : Limit of land is now applied to addLand()

V1.4.6 : Added non-check-worlds

*/


// NOTE : Logo of Eclipse KEPLER is great!

class EconomyLand implements Plugin {
	private $api, $land, $start, $end;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] Cannot find EconomyAPI");
			$this->api->console->defaultCommands("stop", "", "plugin", false);
			return;
		}
		$this->path = $this->api->plugin->configPath($this);
		$this->api->plugin->createConfig($this, array(
			"handler-priority" => 10,
			"white-world-protection" => array(),
			"non-check-worlds" => array(),
			"player-land-limit" => "NaN"
		));
		$this->createMessageConfig();
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		$priority = $this->config["handler-priority"];
		if(!is_numeric($this->config["handler-priority"])){
			$priority = 10;
		}
		$this->createData();
		$this->convertData();
		$suc = EconomyLandAPI::set($this);
		if($suc === false){
			console(FORMAT_RED."[EconomyLand] Unknown error has been found.");
			return;
		}
		$wcmd = array("startp", "endp", "land", "landsell");
		foreach($wcmd as $c){
			$this->api->ban->cmdWhitelist($c);
		}
		$this->cmd = array("startp" => "", "endp" => "", "land" => "<list | here | move | invite | invitee | give | buy | whose> ", "landsell" => "<here | land number>");
		foreach($this->cmd as $c => $h){
			$this->api->console->register($c, $h, array($this, "commandHandler"));
		}
		foreach($wcmd as $c){
			$this->api->ban->cmdWhitelist($c);
		}
		$this->api->addHandler("player.pickup", array($this, "permissions"), $priority);
		$this->api->addHandler("player.block.touch", array($this, "permissions"), $priority);
		$this->api->economy->EconomySRegister("EconomyLand");
	}
	
	private function convertData(){
		if(is_file(DATA_PATH."plugins/EconomyLand/LandData.yml")){
			$temp = (new Config(DATA_PATH."plugins/EconomyLand/LandData.yml", CONFIG_YAML))->getAll();
			$cnt = 0;
			foreach($temp as $data){
				$invitee = "";
				if(is_array($data["invitee"])){
					foreach($data["invitee"] as $val){
						$invitee .= $val.",";
					}
				}
				$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, level, price, owner, invitee) VALUES ({$data["startX"]}, {$data["x"]}, {$data["startZ"]}, {$data["z"]}, '{$data["level"]}', {$data["price"]}, '{$data["owner"]}', '$invitee');");
				++$cnt;
			}
			$m = $cnt > 1 ? "":"m";
			console(FORMAT_AQUA."[EconomyLand] Converted $cnt of land data{$m}");
			@unlink(DATA_PATH."plugins/EconomyLand/LandData.yml");
			unset($temp);
		}
	}
	
	public function getLands(){
		return clone $this->land;
	}
	
	private function createMessageConfig(){
		$this->lang = new Config(DATA_PATH."plugins/EconomyLand/language.properties", CONFIG_YAML, array(
			"sold-land" => "Has been sold the land for $%1",
			"not-my-land" => "Here is not your land",
			"no-one-owned" => "Anyone doesn't have this land",
			"sold-land" => "Has been sold the land for $%1",
			"not-your-land" => "Land number %1 is not your land",
			"no-land-found" => "There's no land numbered %1",
			"land-corrupted" => "Land number %1 is corrupted",
			"fail-moving" => "Failed moving to land %1",
			"success-moving" => "Has been moved to land %1",
			"land-list-top" => "Showing land list page %1 of %2\\n",
			"land-list-format" => "#%1 Width : %2 m^2 | Owner : %3\\n",
			"here-land" => "#%1 Here is %2's land",
			"land-num-must-numeric" => "Land number must numeric",
			"not-invitee" => "%1 is not invitee of your land",
			"already-invitee" => "Player %1 is already invitee of your land",
			"removed-invitee" => "Has been removed %1 from land %2",
			"success-invite" => "%1 is now invitee of your land",
			"player-not-connected" => "Player %1 is not connected",
			"cannot-give-land-myself" => "You can't give your land yourself",
			"gave-land" => "Has been gave land %1 for %2",
			"got-land" => "[EconomyLand] %1 gave you land %2",
			"land-limit" => "You have %1 lands. The limit of land is %2",
			"set-first-position" => "Please set first position",
			"set-second-position" => "Please set second position",
			"land-around-here" => "There are %1's land around here",
			"no-money-to-buy-land" => "You don't have money to buy this land",
			"bought-land" => "Has been bought land for $%1",
			"first-position-saved" => "First position saved",
			"second-position-saved" => "Second position saved",
			"cant-set-position-in-different-world" => "You can't set position in different world",
			"confirm-buy-land" => "Land price : $%1\\nBuy land with command /land buy",
			"no-permission" => "You don't have permission to edit this land. Owner : %1",
			"not-owned" => "You must buy land to edit this block"
		));
	}
	
	public function getMessage($key, $value = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3", "\\n"), array($value[0], $value[1], $value[2], "\n"), $this->lang->get($key));
		}
		return "There's no message named \"$key\"";
	}
	
	public function commandHandler($cmd, $param, $issuer, $alias){
		$output = "";
		if(!$issuer instanceof Player){
			return "Please run this command in-game.\n";
		}
		switch ($cmd){
		case "landsell":
			switch ($param[0]){
			case "here":
				$x = $issuer->entity->x;
				$z = $issuer->entity->z;
				$result = $this->land->query("SELECT * FROM land WHERE (startX < $x AND endX > $x) AND (startZ < $z AND endZ > $z) AND level = '{$issuer->level->getName()}'");
				$info = $result->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)){
					$output .= $this->getMessage("no-one-owned");
					break;
				}
				if($info["owner"] !== $issuer->username){
					$output .= $this->getMessage("not-my-land");
				}else{
					$this->api->economy->takeMoney($issuer, $info["price"] / 2);
					$output .= $this->getMessage("sold-land", array(($info["price"] / 2), "", ""));
					$this->land->exec("DELETE FROM land WHERE ID = {$info["ID"]}");
				}
				break;
			default:
				$p = $param[0];
				if(is_numeric($p)){
					$info = $this->land->query("SELECT * FROM land WHERE ID = $p")->fetchArray(SQLITE3_ASSOC);
					if(is_bool($info)){
						$output .= "Usage: /landsell <here | land number>";
						break;
					}
					if($info["owner"] === $issuer->username or $this->api->ban->isOp($issuer->username)){
						$this->api->economy->takeMoney($issuer, $info["price"] / 2);
						$output .= $this->getMessage("sold-land", array(($info["price"] / 2), "", ""));
						$this->land->exec("DELETE FROM land WHERE ID = $p");
					}else{
						$output .= $this->getMessage("not-your-land", array($p, $info["owner"], ""));
					}
				}else{
					$output .= $this->getMessage("no-land-found", array($p, "", ""));
				}
			}
			break;
		case "land":
			$p = array_shift($param);
			if($p == "move"){
				$num = array_shift($param);
				if(trim($num) == ""){
					$output .= "Usage: /land move <land num>";
					break;
				}
				if(!is_numeric($num)){
					return "Usage: /land move <land num>";
				}
				$result = $this->land->query("SELECT * FROM land WHERE ID = $num");
				$info = $result->fetchArray(SQLITE3_ASSOC);
				if($info === true or $info === false){
					$output .= $this->getMessage("no-land-found", array($num, "", ""));
					break;
				}
				$level = $this->api->level->get($info["level"]);
				if(!$level instanceof Level){
					$output .= $this->getMessage("land-corrupted", array($num, "", ""));
					break;
				}
				$x = (int) $info["startX"] + (($info["endX"] - $info["startX"]) / 2);
				$z = (int) $info["startZ"] + (($info["endZ"] - $info["startZ"]) / 2);
				for($y = 1;; $y++){
					if($level->level->getBlock(new Position($x, $y, $z, $level))[0] === 0){
						break;
					}
					if($cnt === 5){
						break;
					}
					if($y > 255){
						++$cnt;
						++$x;
						--$z;
						$y = 1;
						continue;
					}
				}
				$issuer->teleport(new Position($x, $y, $z, $level));
				$output .= $this->getMessage("success-moving", array($num, "", ""));
			}elseif($p == "list"){
				$page = isset($param[0]) ? (int) $param[0] : 1;
				$result = $this->land->query("SELECT * FROM land");
				if(is_bool($result)) $land = array();
				else{
					$land = array();
					while(($d = $result->fetchArray(SQLITE3_ASSOC)) !== false){
						$land[] = $d;
					}
				}
				$max = ceil(count($land) / 5);
				$pro = 1;
				$output .= $this->getMessage("land-list-top", array($page, $max, ""));
				$current = 1;
				foreach($land as $l){
					$cur = (int) ceil($current / 5);
					if($cur > $page) 
						continue;
					if($pro == 6) 
						break;
					if($page == $cur){
						$output .= $this->getMessage("land-list-format", array($l["ID"], ($l["endX"] - $l["startX"]) * ($l["endZ"] - $l["startZ"]), $l["owner"]));
						$pro++;
					}
					$current++;
				}
			}elseif($p == "here"){
				$x = $issuer->entity->x;
				$z = $issuer->entity->z;
				$result = $this->land->query("SELECT * FROM land WHERE startX < $x AND endX > $x AND startZ < $z AND endZ > $z AND level = '{$issuer->level->getName()}'");
				$info = $result->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)){
					$output .= $this->getMessage("no-one-owned");
					break;
				}
				$output .= $this->getMessage("here-land", array($info["ID"], $info["owner"]));
			}elseif($p == "invite"){
				$landnum = array_shift($param);
				$player = array_shift($param);
				if(trim($player) == "" or trim($landnum) == ""){
					$output .= "Usage : /land <invite> [land number] [(r:)player]";
					break;
				}
				if(!is_numeric($landnum)){
					$output .= $this->getMessage("land-num-must-numeric", array($landnum, "", ""));
					break;
				}
				$result = $this->land->query("SELECT * FROM land WHERE ID = $landnum");
				$info = $result->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)){
					$output .= $this->getMessage("no-land-found", array($landnum, "", ""));
					break;
				}elseif($info["owner"] !== $issuer->username){
					$output .= $this->getMessage("not-your-land", array($landnum, "", ""));
					break;
				}elseif(substr($player, 0, 2) === "r:"){
					$player = substr($player, 2);
					if(strpos($info["invitee"], ",".$player.",") === false){
						$output .= $this->getMessage("not-invitee", array($player, $landnum, ""));
						break;
					}
					$this->land->exec("UPDATE land SET invitee = '".str_replace($player.",", "", $info["invitee"])."' WHERE ID = {$info["ID"]};");
					$output .= $this->getMessage("removed-invitee", array($player, $landnum, ""));
				}else{
					if(strpos($info["invitee"], ",".$player.",") !== false){
						$output .= $this->getMessage("already-invitee", array($player, "", ""));
						break;
					}
					$this->land->exec("UPDATE land SET invitee = '".$info["invitee"].$player.",' WHERE ID = {$info["ID"]};");
					$output .= $this->getMessage("success-invite", array($player, $landnum, ""));
				}
			}elseif($p == "invitee"){
				$landnum = array_shift($param);
				if(trim($landnum) == "" or !is_numeric($landnum)){
					$output .= "Usage: /land invitee <land number>";
					break;
				}
				$info = $this->land->query("SELECT invitee FROM land WHERE ID = $landnum")->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)){
					$output .= $this->getMessage("no-land-found", array($landnum, "", ""));
					break;
				}
				$output .= "Invitee of land #$landnum : \n";
				$output .= substr($info["invitee"], 1);
				$output = str_replace(",", ", ", substr($output, 0, -1));
			}elseif($p == "give"){
				$player = array_shift($param);
				$landnum = array_shift($param);
				if(trim($player) == "" or trim($landnum) == "" or !is_numeric($landnum)){
					$output .= "Usage: /$cmd give <player> <land number>";
					break;
				}
				$username = $player;
				$player = $this->api->player->get($username, false);
				if(!$player instanceof Player){
					$player = $this->api->player->get($username);
					if(!$player instanceof Player){
						$output .= $this->getMessage("player-not-connected", array($username, "", ""));
						break;
					}
				}
				$info = $this->land->query("SELECT * FROM land WHERE ID = $landnum")->fetchArray(SQLITE3_ASSOC);
				if(is_bool($info)){
					$output .= $this->getMessage("no-land-found", array($landnum, "", ""));
					break;
				}
				if($issuer->username !== $info["owner"] and !$this->api->ban->isOp($issuer->iusername)){
					$output .= $this->getMessage("not-your-land", array($landnum, "", ""));
				}else{
					if($issuer->username === $player->username){
						$output .= $this->getMessage("cannot-give-land-myself");
					}else{
						$this->land->exec("UPDATE land SET owner = '{$player->username}' WHERE ID = {$info["ID"]}");
						$output .= $this->getMessage("gave-land", array($landnum, $player->username));
						$player->sendChat($this->getMessage("got-land", array($issuer->username, $landnum)));
					}
				}
			}elseif($p == "buy"){
				$result = $this->land->query("SELECT * FROM land WHERE owner = '{$issuer->username}'");
				$cnt = 0;
				if(is_numeric($this->config["player-land-limit"])){
					while($result->fetchArray(SQLITE3_ASSOC) !== false){
						++$cnt;
						if($cnt >= $this->config["player-land-limit"]){
							$output .= $this->getMessage("land-limit", array($cnt, $this->config["player-land-limit"]));
							break 2;
						}
					}
				}
				if(!isset($this->start[$issuer->username])){
					$output .= $this->getMessage("set-first-position");
					break;
				}elseif(!isset($this->end[$issuer->username])){
					$output .= $this->getMessage("set-second-position");
					break;
				}
				$l = $this->start[$issuer->username];
				$endp = $this->end[$issuer->username];
				$startX = (int) $l["x"];
				$endX = (int) $endp["x"];
				$startZ = (int) $l["z"];
				$endZ = (int) $endp["z"];
				if($startX > $endX){ // startX 가 endX 보다 클 경우 둘이 바꿔치기
					$backup = $startX;
					$startX = $endX;
					$endX = $backup;
				}
				if($startZ > $endZ){ // startZ 가 endZ 보다 클 경우 둘이 바꿔치기
					$backup = $startZ;
					$startZ = $endZ;
					$endZ = $backup;
				}
				$startX--;
				$endX++;
				$startZ--;
				$endZ++;
				$result = $this->land->query("SELECT * FROM land WHERE startX <= $endX AND endX >= $endX AND startZ <= $endZ AND endZ >= $endZ AND level = '{$issuer->level->getName()}'")->fetchArray(SQLITE3_ASSOC);
				if(!is_bool($result)){
					$output .= $this->getMessage("land-around-here", array($result["owner"], "", ""));
					break;
				}
				$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * 100;
				if($this->api->economy->useMoney($issuer, $price) == false){
					$output .= $this->getMessage("no-money-to-buy-land");
					break;
				}
				$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, owner, level, price, invitee) VALUES ($startX, $endX, $startZ, $endZ, '{$issuer->username}', '{$this->start[$issuer->username]["level"]}', $price, ',')");
				unset($this->start[$issuer->username]);
				unset($this->end[$issuer->username]);
				$output .= $this->getMessage("bought-land", array($price, "", ""));
			}elseif($p === "whose"){
				$player = array_shift($param);
				$alike = true;
				if(str_replace(" ", "", $player) === ""){
					$player = $issuer->username;
					$alike = false;
				}
				$result = $this->land->query("SELECT * FROM land WHERE owner ".($alike ? "LIKE '%".$player."%'" : "= '".$player."'"));
				$output .= "Results from query : $player\n";
				while(($info = $result->fetchArray(SQLITE3_ASSOC)) !== false){
					$output .= $this->getMessage("land-list-format", array($info["ID"], ($info["endX"] - $info["startX"]) * ($info["endZ"] - $info["startZ"]), $info["owner"]));
				}
			}else{
				$output .= "Usage: /land ".$this->cmd["land"];
			}
			break;
		case "startp":
			$x = (int) $issuer->entity->x;
			$z = (int) $issuer->entity->z;
			$level = $issuer->level->getName();
			$this->start[$issuer->username] = array("x" => $x, "z" => $z, "level" => $level);
			$output .= $this->getMessage("first-position-saved");
			break;
		case "endp":
			if(!isset($this->start[$issuer->username])){
				$output .= $this->getMessage("set-first-position");
				break;
			}
			if($issuer->level->getName() !== $this->start[$issuer->username]["level"]){
				$output .= $this->getMessage("cant-set-position-in-different-world");
				break;
			}
			$this->end[$issuer->username] = null;
			unset($this->end[$issuer->username]);
			$startX = $this->start[$issuer->username]["x"];
			$startZ = $this->start[$issuer->username]["z"];
			$endX = (int) $issuer->entity->x;
			$endZ = (int) $issuer->entity->z;
			$this->end[$issuer->username] = array(
				"x" => $endX,
				"z" => $endZ
			);
			if($startX > $endX){
				$temp = $endX;
				$endX = $startX;
				$startX = $temp;
			}
			if($startZ > $endZ){
				$temp = $endZ;
				$endZ = $startZ;
				$startZ = $temp;
			}
			$startX--;
			$endX++;
			$startZ--;
			$endZ++;
			$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * 100;
			$output .= $this->getMessage("confirm-buy-land", array($price, "", ""));
			break;
		}
		return $output."\n";
	}
	
	public function permissions($data, $event){
		switch ($event){
			case "player.block.touch":
				$x = $data["target"]->x;
				$y = $data["target"]->y;
				$z = $data["target"]->z;
				$level = $data["target"]->level->getName();
				break;
			case "player.pickup":
				$x = $data["entity"]->x;
				$y = $data["entity"]->y;
				$z = $data["entity"]->z;
				$level = $data["entity"]->level->getName();
				break;
		}
		if(in_array($level, $this->config["non-check-worlds"])){
			return;
		}
		$exist = false;
		$result = $this->land->query("SELECT owner,invitee FROM land WHERE level = '$level' AND endX > $x AND endZ > $z AND startX < $x AND startZ < $z");
		$info = $result->fetchArray(SQLITE3_ASSOC);
		if(!is_array($info)) goto checkLand;
		if($info["owner"] != $data["player"]->username and !$this->api->ban->isOp($data["player"]->iusername) and strpos($info["invitee"], ",".$data["player"]->iusername.",") === false){
			$data["player"]->sendChat($this->getMessage("no-permission", array($info["owner"], "", "")));
			return false;
		}else{
			$exist = true;
		}
		checkLand:
		if($this->config["white-world-protection"]){
			if(!$exist and in_array($level, $this->config["white-world-protection"]) and !$this->api->ban->isOp($data["player"]->iusername)){
				$data["player"]->sendChat($this->getMessage("not-owned"));
				return false;
			}
		}
	}
	
	private function createData(){
		try{
			$this->land = new \SQLite3($this->path."LandData.sqlite3");
			$this->land->exec("CREATE TABLE IF NOT EXISTS land(
				ID INTEGER PRIMARY KEY AUTOINCREMENT,
				startX INTEGER NOT NULL,
				startZ INTEGER NOT NULL,
				endX INTEGER NOT NULL,
				endZ INTEGER NOT NULL,
				level TEXT NOT NULL,
				owner TEXT NOT NULL,
				invitee TEXT NOT NULL,
				price INTEGER NOT NULL
			);");
		}catch(Exception $e){
			console(FORMAT_DARK_RED."[EconomyLand] Failed to open SQLite3 database.\n$e");
		}
	}
	
	public function editLand($data){
		$result = $this->land->query("SELECT ID FROM land WHERE endX = {$data["x"]} AND endZ = {$data["z"]} AND startX = {$data["startX"]} AND startZ = {$data["startZ"]} AND level = '{$data["level"]}'");
		$info = $result->fetchArray(SQLITE3_ASSOC);
		if(!is_bool($info)){
			$this->land->exec("UPDATE land SET owner = '{$data["owner"]}', invitee = '{$data["invitee"]}', price = {$data["price"]})");
			return true;
		}
		return false;
	}
	
	public function addLand($data){
		$result = $this->land->query("SELECT * FROM land WHERE owner = '{$data["owner"]}'");
		if(is_numeric($this->config["player-land-limit"])){
			$cnt = 0;
			while($result->fetchArray(SQLITE3_ASSOC) !== false){
				++$cnt;
				if($cnt >= $this->config["player-land-limit"]){
					return 0;
				}
			}
		}
		if($this->checkLandOverlap($data["startX"], $data["startZ"], $data["endX"], $data["endZ"])){
			return -1;
		}
		$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, level, owner, price, invitee) VALUES ({$data["startX"]}, {$data["endX"]}, {$data["startZ"]}, {$data["endZ"]}, '{$data["level"]}', '{$data["owner"]}', {$data["price"]}, '')");
		return 1;
	}
	
	
	/*
	@param int $x
	@param int $y
	@param int $z
	@param int $z
	
	@return boolean
	*/
	public function checkLandOverlap($startX, $startZ, $endX, $endZ, $level){
		$result = $this->land->query("SELECT * FROM land WHERE startX <= $endX AND endX >= $endX AND startZ <= $endZ AND endZ >= $endZ AND level = '$level'")->fetchArray(SQLITE3_ASSOC);
		return !is_bool($result);
	}
	
	public function __destruct(){}
}



class EconomyLandAPI {
	public static $a;
	public static function set(EconomyLand $l){
		if(EconomyLandAPI::$a instanceof EconomyLand){
			return false;
		}
		EconomyLandAPI::$a = $l;
		return true;
	}
	
	public static function getLands(){
		return EconomyLandAPI::$a->getLands();
	}
	
	public static function myLand($owner){
		$ret = array();
		$result = EconomyAPI::$a->getLands()->query("SELECT * FROM WHERE owner = '$owner'");
		while(($d = $result->fetchArray(SQLITE3_ASSOC)) !== false){
			$ret[] = $d;
		}
		return $ret;
	}
	
	public static function editLand($data){
		if(is_array($data)){
			return EconomyLandAPI::$a->editLand($data);
		}
		return false;
	}
}
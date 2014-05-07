<?php

/*
__PocketMine Plugin__
name=EconomyPShop
version=1.1.8
author=onebone
apiversion=12,13
class=EconomyPShop
*/
/*

※ 이 플러그인은 다소 저장 공간을 많이 차지할 수 있습니다.

=====CHANGE LOG======
V1.0.0 : First Release

V1.0.1 : Texts changes immediately

V1.0.2 : Now works at DroidPocketMine

V1.0.3 : You have to tap twice to buy item.

V1.1.0 : Added ItemCloud Service

V1.1.1 : Bug fix

V1.1.2 : Minor bug fixed

V1.1.3 : Minor bug fixed

V1.1.4 : Changed the buy message to send the message once per one item

V1.1.5 : Compatible with API 11

V1.1.6 : OPs can destroy others' shop

V1.1.7 : Compatible with API 12 (Amai Beetroot)

V1.1.8 : Some bug about parsing of data file

*/

class EconomyPShop implements Plugin{
	private $api, $item, $buy, $shop, $itemcloud, $itemcloud_resisdent;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->item = array();
		$this->buy = array();
		$this->shop = array();
		$this->itemcloud = array();
		$this->itemcloud_resisdent = array();
		$this->buyslot = array();
		$this->sellslot = array();
	}
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI not found");
			$this->api->console->defaultCommands("stop", "", "EconomyS", false);
			return;
		}
		$this->path = $this->api->plugin->configPath($this);
		//$this->api->addHandler("player.quit", array($this, "handle"));
		$this->api->addHandler("player.spawn", array($this, "handle"));
		$this->api->event("server.close", array($this, "handle"));
		$this->api->event("tile.update", array($this, "generateHandler"));
		$this->data = new Config($this->path."Shops.yml", CONFIG_YAML, array());
		$load = $this->api->plugin->readYAML($this->path."Shops.yml");
		if(is_array($load)){
			foreach($load as $k => $v){
				$this->shop[$k] = $v;
			}
		}
		$this->api->addHandler("player.block.touch", array($this, "generateHandler"));
		$cmd = array(
			"itemcloud" => "<register | upload | download | buyslot | removeslot | list | myslot>"
		);
		$wcmd = array(
			"itemcloud"
		);	
		foreach($wcmd as $c){
			$this->api->ban->cmdWhitelist($c);
		}
		foreach($cmd as $c => $h){
			$this->api->console->register($c, $h, array($this, "commandHandler"));
		}
		$config = new Config($this->path."pshop.properties", CONFIG_LIST, array(
			"auto-save" => true
		));
		if($config->get("auto-save")){
			$this->api->schedule(18000, array($this, "save"), array(), true);
		}
		$this->api->addHandler("console.command.save-all", array($this, "handle"));
		if(!is_file($this->path."item.dat")){
			file_put_contents($this->path."item.dat", serialize(array()));
		}
		if(!is_file($this->path."buys.dat")){
			file_put_contents($this->path."buys.dat", serialize(array()));
		}
	/*	if(!is_file($this->path."data.dat")){
			file_put_contents($this->path."data.dat", serialize(array()));
		}*/
		if(!is_file($this->path."ItemCloud.dat")){
			file_put_contents($this->path."ItemCloud.dat", serialize(array()));
		}
		$itemcloud = unserialize(file_get_contents($this->path."ItemCloud.dat"));
		foreach($itemcloud as $key => $value){
			$this->itemcloud_resisdent[$key] = new TemporItemCloud($value[0], $key, $value[2]);
		}
		$this->buy = unserialize(file_get_contents($this->path."buys.dat"));
		$this->item = unserialize(file_get_contents($this->path."item.dat"));
	//	$this->gm = unserialize(file_get_contents($this->path."data.dat"));
		$this->api->economy->EconomySRegister("EconomyPShop");
	}
	public function __destruct(){}
	public function commandHandler($cmd, $param, $issuer, $alias){
		$output = "[ItemCloud] ";
		switch($cmd){
			case "itemcloud":
			if(!$issuer instanceof Player){
				return "Please run this command in-game.\n";
			}
			$sub = array_shift($param);
			if($sub !== "register" and trim($sub) != ""){
				if(!isset($this->itemcloud[$issuer->username]) or !$this->itemcloud[$issuer->username] instanceof ItemCloud){
					return "[ItemCloud] Please register to the ItemCloud first.";				
				}
			}
			switch($sub){
				case "myslot":
				$output .= "Your slot : ".$this->itemcloud[$issuer->username]->getData("slots");;
				break;
				case "register":
				if(!isset($this->itemcloud[$issuer->username]) or !$this->itemcloud[$issuer->username] instanceof ItemCloud){
					$this->itemcloud[$issuer->username] = new ItemCloud(array(), $issuer);
					$output .= "Has been registered to the ItemCloud service.";
				}else{
					$output .= "You already has ItemCloud account.";
				}
				break;
				case "upload":
				$item = array_shift($param);
				$amount = array_shift($param);
				if($issuer->getGamemode() == "creative"){
					$output .= "You are in creative mode.";
					break;
				}
				if(trim($item) == "" or trim($amount) == ""){
					$output .= "Usage: /itemcloud <upload> [item[:damage]] [amount]";
					break;
				}
				$e = explode(":", $item);
				$e[1] = isset($e[1]) ? $e[1] : 0;
				if(!is_numeric($e[0]) or !is_numeric($e[1]) or !is_numeric($amount)){
					$output .= "Invalid item";
					break;
				}
				$can = $this->itemcloud[$issuer->username]->addItem((int) $e[0], (int) $e[1], (int) $amount);
				if($can){
					$output .= "Has been uploaded $amount of {$e[0]}:{$e[1]}";
				}else{
					$output .= "You don't have items to upload or don't have slot.";
				}
				break;
				case "download":
				$item = array_shift($param);
				$amount = array_shift($param);
				if($issuer->getGamemode() == "creative"){
					$output .= "You are in creative mode.";
					break;
				}
				if(trim($item) == "" or trim($amount) == ""){
					$output .= "Usage: /itemcloud <download> [item[:damage]] [amount]";
					break;
				}
				$e = explode(":", $item);
				$e[1] = isset($e[1]) ? $e[1] : 0;
				if(!is_numeric($e[0]) or !is_numeric($e[1]) or !is_numeric($amount)){
					$output .= "Invalid item";
					break;
				}
				$can = $this->itemcloud[$issuer->username]->removeItem((int) $e[0], (int) $e[1], (int) $amount);
				if($can){
					$issuer->addItem((int) $e[0], (int) $e[1], $amount);
					$output .= "Has been downloaded $amount of {$e[0]}:{$e[1]}";
				}else{
					$output .= "There are no enough items to download.";
				}
				break;
				case "buyslot":
				$count = array_shift($param);
				if(trim($count) == "" or !is_numeric($count) and !isset($this->buyslot[$issuer->username])){
					$output .= "Usage: /itemcloud <buyslot> [(count)] | accept | cancel]";
					break;
				}
				if(isset($this->buyslot[$issuer->username])){
					if($count == "accept"){
						$can = $this->api->economy->useMoney($issuer, $this->buyslot[$issuer->username] * 100);
						if($can){
							$this->itemcloud[$issuer->username]->increaseSlots($this->buyslot[$issuer->username]);
							$output .= "Has been bought ".$this->buyslot[$issuer->username]."slot(s)";
							unset($this->buyslot[$issuer->username]);
						}else{
							$output .= "You don't have money to buy ".$this->buyslot[$issuer->username]."slot(s)";
							unset($this->buyslot[$issuer->username]);
						}
					}elseif($count == "cancel"){
						$output .= "Cancelled buying.";
						unset($this->buyslot[$issuer->username]);
						break;
					}else{
						$output .= "There are no parameter $count";
					}
				}elseif(!isset($this->buyslot[$issuer->username])){
					$output .= "Required money is ".($count * 100)."$. Are you sure to buy?\nUsage: /itemcloud <buyslot> [accept | cancel]";
					$this->buyslot[$issuer->username] = $count;
					break;
				}
				break;
				case "removeslot":
				$count = array_shift($param);
				if(trim($count) == "" or !is_numeric($count) and !isset($this->sellslot[$issuer->username])){
					$output .= "Usage: /itemcloud <sellslot> [(count) | accept | cancel]";
					break;
				}
				if(isset($this->sellslot[$issuer->username])){
				if($count == "accept"){
					$can = $this->itemcloud[$issuer->username]->decreaseSlots($this->sellslot[$issuer->username]);
					if($can){
						$output .= "Has been removed ".$this->sellslot[$issuer->username]." of slot(s).";
						unset($this->sellslot[$issuer->username]);
					}else{
						$output .= "You don't have ".$this->sellslot[$issuer->username]." of slots.";
						unset($this->sellslot[$issuer->username]);
					}
				}elseif($count == "cancel"){
					$output .= "Cancelled";
					unset($this->sellslot[$issuer->username]);
				}else{
					$output .= "There are no parameter $count";
				}
				}elseif(!isset($this->sellslot[$issuer->username])){
					$output .= "We do not give you money when you sell slot. Are you sure to sell?\nUsage: /itemcloud <removeslot> [accept | cancel]";
					$this->sellslot[$issuer->username] = $count;
					break;
				}
				break;
				case "list":
				$page = array_shift($param);
				$page = max(1, $page);
				$items = $this->itemcloud[$issuer->username]->getData("items");
				$max = ceil(count($items) / 5);
				$page = min($page, $max);
				$output .= "Items List : Showing page $page / $max\n";
				$current = 1;
				foreach($items as $item => $amount){
					$cur = ceil($current / 5);
					if($cur == $page){
						$e = explode(":", $item);
						$output .= "Item : $e[0]:$e[1] / Amount : $amount\n";
					}
					$current ++;
				}
				break;
				default:
				$output .= "Usage: /itemcloud <register | upload | download | buyslot | removeslot | list | myslot>";
			}
			break;
		}	
		return $output;
	}
	public function generateHandler(&$data, $event){
		switch($event){
		case "tile.update":
		if($data->class === TILE_SIGN){
			if($data->data["Text1"] == "pshop" or $data->data["Text1"] == "일반상점"){
				$issuer = $this->api->player->get($data->data["creator"]);
				$lang = $data->data["Text1"] == "pshop" ? "english" : "korean";
				$e = explode(":", $data->data["Text3"]);
				if(count($e) < 1){
					$issuer->sendChat($lang == "english" ? "Please check your parameters are all correct." : "오타가 없는지 확인해주십시오.");
					break;
				}
				if(count($e) == 1){
					$e[1] = 0;
				}
				if(!is_numeric($data->data["Text2"]) or !is_numeric($data->data["Text2"])){
					$issuer->sendChat($lang == "english" ? "Please check your parameters are all correct." : "오타가 없는지 확인해주십시오.");
					break;
				}
				$this->shop[] = array(
					"x" => $data->x,
					"y" => $data->y,
					"z" => $data->z,
					"level" => $data->level->getName(),
					"item" => $e[0],
					"meta" => $e[1],
					"price" => $data->data["Text2"],
					"amount" => $data->data["Text4"],
					"owner" => $issuer->username
				);
				if(strlen($issuer->username) > 14){
					$username = substr($issuer->username, 14, 0);
				}else{
					$username = $issuer->username;
				}
				$lang == "english" ? $data->setText($username, $data->data["Text2"]."$", "item : ".$e[0].":".$e[1], "Amount : ".$data->data["Text4"]) : $data->setText($username, $data->data["Text2"]."$", "아이템 : ".$e[0].":" .$e[1], "수량 : ".$data->data["Text4"]);
				$issuer->sendChat($lang == "english" ? "Player shop has been created." : "플레이어용 상점이 생성되었습니다");
			}   
		}
		break;
		case "player.block.touch":
			switch($data["type"]){
				case "break":
				foreach($this->shop as $k => $s){
					if($s["x"] == $data["target"]->x and $s["y"] == $data["target"]->y and $s["z"] == $data["target"]->z and $data["target"]->level->getName() == $s["level"]){
						if($s["owner"] == $data["player"]->username){
							unset($this->shop[$k]);
							$data["player"]->sendChat("Your shop has been closed.");
						}elseif($this->api->ban->isOp($data["player"]->username)){
							unset($this->shop[$k]);
							$data["player"]->sendChat($s["owner"]."'s shop has been closed.");
						}else{
							$data["player"]->sendChat("This is not your shop.");
							return false;
						}
					}
				}
				break 2;
			}
			foreach($this->shop as $s){
				if($s["x"] == $data["target"]->x and $s["y"] == $data["target"]->y and $s["z"] == $data["target"]->z and $s["level"] == $data["target"]->level->getName()){
					if($data["player"]->getGamemode() === "creative"){
						$data["player"]->sendChat("You are in creative mode.");
						break 2;
					}
					$p = $this->api->player->get($s["owner"], false);
					$level = $this->api->level->get($s["level"]);
					if($data["player"]->username == $s["owner"]){
						$data["player"]->sendChat("You can't buy the item from your shop.");
						break 2;
					}
					if((!(isset($this->itemcloud[$s["owner"]])) or !$this->itemcloud[$s["owner"]] instanceof ItemCloud) and (!(isset($this->itemcloud_resisdent[$s["owner"]])) or !$this->itemcloud_resisdent[$s["owner"]] instanceof TemporItemCloud)){
						$data["player"]->sendChat("Shop owner does not have ItemCloud Account.");
						break 2;
					}
					$t = $this->api->tile->get(new Position($s["x"], $s["y"], $s["z"], $level));
					$p = $this->api->player->get($s["owner"], false);
					$exist = false;
					if(isset($this->itemcloud[$s["owner"]]) and $this->itemcloud[$s["owner"]] instanceof ItemCloud){
						$exist = $this->itemcloud[$s["owner"]]->itemExists((int) $s["item"], (int) $s["meta"], (int) $s["amount"]);
					}else{
						$exist = $this->itemcloud_resisdent[$s["owner"]]->itemExists((int) $s["item"], (int) $s["meta"], (int) $s["amount"]);
					}	
					if($exist == false){
						$data["player"]->sendChat("Sorry. There are no item.");
						break 2;
					}
					$can = $this->api->economy->useMoney($data["player"], (int)$s["price"]);
					if($can == false){
						$data["player"]->sendChat("Sorry. You don't have money to buy this.");
						break 2;
					}

					$player = $data["player"];
					if(!isset($this->tap[$data["player"]->username])){
						$this->tap[$data["player"]->username] = array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"]);
						$this->api->schedule(20, array($this, "removeTap"), $data["player"]->username);
						$data["player"]->sendChat("Are you sure to buy this? Tap again to confirm.");
						break;
					}
					if(!($s["x"] == $this->tap[$player->username]["x"] and $s["y"] == $this->tap[$player->username]["y"] and $s["z"] == $this->tap[$player->username]["z"])){
						$player->sendChat("Are you sure to buy this? Tap again to confirm.");
						$this->tap[$player->username] = array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"]);
						$this->api->schedule(20, array($this, "removeTap"), $player->username);
						$this->cancel[$player->username] = true;
						break;
					}
					$this->api->block->commandHandler("give", array($data["player"]->username, $s["item"].":".$s["meta"], $s["amount"]), "EconomyS", false);
					$data["player"]->sendChat("You've been bought ".$s["amount"]." of ".$s["item"].":".$s["meta"]." for ".$s["price"]."$ at ".$s["owner"]."'s shop.");
					unset($this->tap[$data["player"]->username]);
					$cnt = 0;
					if(isset($this->itemcloud[$s["owner"]]) and $this->itemcloud[$s["owner"]] instanceof ItemCloud){
						$this->itemcloud[$s["owner"]]->removeItem((int) $s["item"], (int) $s["meta"], (int) $s["amount"]);
					}else{
						$this->itemcloud_resisdent[$s["owner"]]->removeItem((int) $s["item"], (int) $s["meta"], (int) $s["amount"]);
					}
					if($p){
						$remove = $s["amount"];
						$p->sendChat($data["player"]->username." bought ".$s["amount"]." of ".$s["item"].":".$s["meta"]." for ". $s["price"]."$");
					}else{
						if(isset($this->buy[$s["owner"]]) and is_array($this->buy[$s["owner"]])){
							foreach($this->buy[$s["owner"]] as $key => &$value){
								if($value["item"] == $s["item"] and $value["meta"] == $s["meta"]){
									$value["amount"] += $s["amount"];
									break 2;
								}
							}
						}
						$this->buy[$s["owner"]][] = array(
							"player" => $data["player"]->username,
							"item" => $s["item"],
							"meta" => $s["meta"],
							"amount" => $s["amount"]
						);					
					}
					$this->api->economy->takeMoney($s["owner"], $s["price"]);
				}
			}
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
	public function handle(&$data, $event){
		switch($event){
		case "console.command.save-all":
		$this->save();
		break;
		case "player.spawn":
		if($data->getGamemode() == "creative"){
			break;
		}elseif(isset($this->itemcloud_resisdent[$data->username])){
			$d = $this->itemcloud_resisdent[$data->username]->getAll();
			$this->itemcloud[$data->username] = new ItemCloud($d[0], $data, $d[2]);
			unset($this->itemcloud_resisdent[$data->username]);
		}
		foreach($this->buy as $k => &$b){
			if($k == $data->username){
				foreach($b as $key => $a){
					$data->sendChat($a["player"] . " bought ".$a["amount"]." of ".$a["item"].":".$a["meta"]." from your shop.");
					unset($b[$key]);
				}
			}
		}
		break;
		case "server.close":
		$this->save();
		break;
		}
	}
	public function save(){
		/*foreach($this->api->player->online() as $o){
		$p = $this->api->player->get($o);
		$this->item[$p->username] = array();
		foreach($p->inventory as $slot => $item){
			$this->item[$p->username][(int)$slot] = array(
				"item" => $item->getID(),
				"meta" => $item->getMetadata(),
				"amount" => $item->count
				);
			}
			$this->gm[$p->username] = $p->getGamemode();
		}*/
		$save = array();
		foreach($this->itemcloud_resisdent as $key => $value){
			$data = $this->itemcloud_resisdent[$key]->getAll();
			$save[$key] = array(
				$data[0],
				$data[1],
				$data[2]
			);
		}
		foreach($this->itemcloud as $key => $value){
			$data = $this->itemcloud[$key]->getAll();
			$save[$key] = array(
				$data[0],
				$data[1],
				$data[2]
			);
		}
		file_put_contents($this->path."ItemCloud.dat", serialize($save));
		file_put_contents($this->path."buys.dat", serialize($this->buy));
	//	file_put_contents($this->path."data.dat", serialize($this->gm));
		file_put_contents($this->path."item.dat", serialize($this->item));
		$this->data->setAll($this->shop);
		$this->data->save();
		return true;
	}
}

class TemporItemCloud{ // Temporal Item Cloud (When player is not on-line)
	private $items, $player, $slot;

	public function __construct($items, $p, $slots = 10){
		if($p instanceof Player){
			$player = $p->username;
		}else{
			$player = $p;
		}
		$this->items = $items;
		$this->player = $player;
		$this->slots = $slots;
		return true;
	}
	public function getAll(){
		$ret[0] = $this->items; 
		$ret[1] = $this->player;
		$ret[2] = $this->slots; // returns all of variables
		return $ret;
	}
	
	public function removeItem($item, $damage = 0, $amount = 64){
		$cnt = 0;
		foreach($this->items as $s => $i){
			$e = explode(":", $s);
			if((int) $e[0] == (int) $item and (int) $e[1] == (int) $damage){
				$cnt += $i;
			}
		}
		if((int) $cnt < (int) $amount){
			return false;
		}
		$this->items[$item.":".$damage] -= $amount;
		if($this->items[$item.":".$damage] == 0){
			unset($this->items[$item.":".$damage]);
		}
		return true;
	}
	
	public function itemExists($item, $damage, $amount){
		$cnt = 0;
		foreach($this->items as $i => $a){
			$e = explode(":", $i);
			if($e[0] == $item and $e[1] == $damage){
				$cnt += $a;
			}
		}
		if($amount <= $cnt){
			return true;
		}else{
			return false;
		}
	}
	
	public function getData($data){
		if(isset($this->$data)){
			return $this->{$data};
		}else{
			return false;
		}
	}
}

class ItemCloud{ // ItemCloud Service
	private $items, $player, $slots;
	
	public function __construct($items, $p, $slots = 10){
		if($p instanceof Player){
			$player = $p->username;
		}else{
			$player = $p;
		}
		$this->items = $items;
		$this->player = $player;
		$this->slots = $slots;
		return true;
	}
	
	public function addItem($item, $damage = 0, $amount = 64, $removeInv = true){
		$cnt = 0;
		$player = ServerAPI::request()->api->player->get($this->player, false);
		if($player == false){
			return false;
		}
		if((int) count($this->items) + 1 > (int) $this->slots) return false;
		if($item == 0 or $amount == 0) return false;
		if($removeInv){
			foreach($player->inventory as $s => $i){
				if($i->getID() === (int) $item and $i->getMetadata() === (int) $damage){
					$cnt += $i->count;
				}
			}
			if((int) $amount > (int) $cnt){
				return false;
			}
		}
		if(isset($this->items[$item.":".$damage])){
			$this->items[$item.":".$damage] += $amount;
		}else{
			$this->items[$item.":".$damage] = $amount;
		}
		if($removeInv){
			foreach($player->inventory as $s => $i){
				if($i->getID() == (int) $item and $i->getMetadata() == (int) $damage){
					if($i->count >= (int) $amount){
						$player->removeItem((int) $i->getID(), (int) $i->getMetadata(), (int) $amount);
						break;
					}else{
						$amount -= $i->count;
						$player->removeItem((int) $i->getID(), (int) $i->getMetadata(), (int) $i->count);
					}
				}
			}
		}
		return true;
	}
	
	public function removeItem($item, $damage = 0, $amount = 64){
		$cnt = 0;
		foreach($this->items as $s => $i){
			$e = explode(":", $s);
			if((int) $e[0] == (int) $item and (int) $e[1] == (int) $damage){
				$cnt += $i;
			}
		}
		if((int) $cnt < (int) $amount){
			return false;
		}
		$this->items[$item.":".$damage] -= $amount;
		if($this->items[$item.":".$damage] == 0){
			unset($this->items[$item.":".$damage]);
		}
		return true;
	}
	
	public function increaseSlots($slot = 1){
		if($slot <= 0) return false;
		$this->slots += $slot;
	}
	
	public function decreaseSlots($slot = 1){
		if($slot <= 0 or $this->slots < $slot) return false;
		$player = ServerAPI::request()->api->player->get($this->player, false);
		if($player == false) return false;
		if($this->slots - count($this->items) >= $slot){
			$this->slots -= $slot;
		}else{
			$backup = $this->slot;
			$this->slot -= ($this->slot - count($this->items));
			$slot -= ($backup - count($this->items));
			foreach($this->items as $k => $i){
				if($slot <= 0){
					return true;
				}
				$item = explode(":", $k);
				$player->addItem((int) $item[0], (int) $item[1], $i);
				unset($this->items[$k]);
				$this->slot--;
				$slot--;
			}
		}
		return true;
	}
	
	public function getAll(){
		$ret[0] = $this->items; 
		$ret[1] = $this->player;
		$ret[2] = $this->slots; // returns all of variables
		return $ret;
	}
	
	public function itemExists($item, $damage, $amount){
		$cnt = 0;
		foreach($this->items as $i => $a){
			$e = explode(":", $i);
			if($e[0] == $item and $e[1] == $damage){
				$cnt += $a;
			}
		}
		if($amount <= $cnt){
			return true;
		}else{
			return false;
		}
	}
	
	public function getData($data){
		if(isset($this->$data)){
			return $this->{$data};
		}else{
			return false;
		}
	}
}
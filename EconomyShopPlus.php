<?php
/*
__PocketMine Plugin__
name=EconomyShopPlus
version=1.0.6
author=onebone
apiversion=12,13
class=EconomyShopPlus
*/

/*
V1.0.0 CLASSIC : First Release

v1.0.1 : Fixed to work at EconomyShop update

V1.0.2 : Added something

V1.0.3 :
- Fixed bug about exchange
- Deleted invalid code

V1.0.4 : Compatible with API 11

V1.0.5 : Minor bug fix

V1.0.6 : Compatible with API 12 (Amai Beetroot)

*/
class EconomyShopPlus implements Plugin {

	private $api;

	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	public function init(){
		foreach($this->api->plugin->getList() as $p){
			if($p["name"] == "EconomyShop"){
				$ex = true;
			}
		}
		if(!isset($ex)){
			console("[ERROR] EconomyShop not found");
			$this->api->console->defaultCommands("stop", "", "EconomyPlugin", false);
			return;
		}
		$this->path = $this->api->plugin->createConfig($this, array("refresh-time" => 10, "broadcast-refresh" => true, "max-change-rate" => 5));
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		$mul = $this->config["refresh-time"];
		$this->api->schedule(20 * 60 * $mul, array($this, "TimeHandler"), array(), true);
		$this->api->economy->EconomySRegister("EconomyShopPlus");
	}
	public function TimeHandler(){
		$min = $this->config["max-change-rate"] - $this->config["max-change-rate"] * 2;
		$exchge = rand($min, $this->config["max-change-rate"]);
		if(is_array(EconomyShopAPI::getShops())){
			foreach(EconomyShopAPI::getShops() as $k => $s){
				$level = $this->api->level->get($s["level"]);
				if($level instanceof Level){
					$tile = $this->api->tile->get(new Position($s["x"], $s["y"], $s["z"], $level));
					if($tile == false){
						continue;
					}elseif($exchge !== 0){
						$price = $s["price"] + ($s["price"] * ($exchge / 100));
						$price = round($price, 2);
						$tile->setText($tile->data["Text1"], $price."$", $tile->data["Text3"], $tile->data["Text4"]);
						//   EconomyShopPlusHelper::handle($price);
						//   $tile->data["Text2"] = "$price\$";
						EconomyShopAPI::editShop(array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"], "item" => $s["item"], "price" => $price, "amount" => $s["amount"], "level" => $s["level"], "meta" => $s["meta"]));
						continue;
					}
				}
			}
		}
		if($this->config["broadcast-refresh"] == true){
			if($exchge > 0){
				$this->api->chat->broadcast("[EconomyShopPlus] Exchange rate $exchge % up");
			}
			elseif($exchge == 0){
				$this->api->chat->broadcast("[EconomyShopPlus] Exchange rate 0 %");
			}else{
				$exchge = -$exchge;
				$this->api->chat->broadcast("[EconomyShopPlus] Exchange rate $exchge % down");
			}
		}
	}
	public function __destruct(){}

}
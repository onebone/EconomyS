<?php
/*
__PocketMine Plugin__
name=EconomyShopPlus
version=1.0.7
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

V1.0.7 : Compatible with new EconomyShop

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
		$this->api->schedule(20 * 60 * $mul, array($this, "timeHandler"), array(), true);
		$this->api->economy->EconomySRegister("EconomyShopPlus");
	}
	
	public function timeHandler(){
		$exchge = rand(~$this->config["max-change-rate"], $this->config["max-change-rate"]);
		$shop = EconomyShop::getInstance();
		if(is_array($shop->getShops())){
			foreach($shop->getShops() as $k => $s){
				$level = $this->api->level->get($s["level"]);
				if($level instanceof Level){
					$tile = $this->api->tile->get(new Position($s["x"], $s["y"], $s["z"], $level));
					if($tile == false){
						continue;
					}elseif($exchge !== 0){
						$price = $s["price"] + ($s["price"] * ($exchge / 100));
						$price = round($price, 2);
						$tile->setText($tile->data["Text1"], $price."$", $tile->data["Text3"], $tile->data["Text4"]);
						$shop->editShop($s["x"], $s["y"], $s["z"], $s["level"], $s["price"], $s["item"], $s["meta"], $s["amount"]);
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
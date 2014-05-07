<?php
/*
__PocketMine Plugin__
name=EconomySellPlus
version=1.0.3
author=onebone
apiversion=12,13
class=EconomySellPlus
*/

/*
=====CHANGE LOG=====

V1.0.0 : First Release

V1.0.1 : Compatible with API 11

V1.0.2 : Minor bug fix

V1.0.3 : Compatible with API 12 (Amai Beetroot)

*/
class EconomySellPlus implements Plugin {

	private $api;

	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	public function init(){
		foreach($this->api->plugin->getList() as $p){
			if($p["name"] == "EconomySell"){
				$ex = true;
			}
		}
		if(!isset($ex)){
			console("[ERROR] EconomySell not found");
			$this->api->console->defaultCommands("stop", "", "EconomyPlugin", false);
			return;
		}
		$this->path = $this->api->plugin->createConfig($this, array("refresh-time" => 10, "broadcast-refresh" => true, "max-change-rate" => 5));
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		$mul = $this->config["refresh-time"];
		$this->api->schedule(20 * 60 * $mul, array($this, "TimeHandler"), array(), true);
		$this->api->economy->EconomySRegister("EconomySellPlus");
	}
	public function TimeHandler(){
		$min = $this->config["max-change-rate"] - $this->config["max-change-rate"] * 2;
		$exchge = rand($min, $this->config["max-change-rate"]);
		if(is_array(EconomySellAPI::getSells())){
			foreach(EconomySellAPI::getSells() as $k => $s){
				$level = $this->api->level->get($s["level"]);
				if($level instanceof Level){
					$tile = $this->api->tile->get(new Position($s["x"], $s["y"], $s["z"], $level));
					if($tile == false){
						continue;
					}elseif($exchge !== 0){
						$price = $s["cost"] + ($s["cost"] * ($exchge / 100));
						$price = round($price, 2);
						EconomySellAPI::editSell(array("x" => $s["x"], "y" => $s["y"], "z" => $s["z"], "item" => $s["item"], "cost" => $price, "amount" => $s["amount"], "level" => $s["level"], "meta" => $s["meta"]));
						continue;
					}
				}
			}
		}

		if($this->config["broadcast-refresh"] == true){
			if($exchge > 0){
				$this->api->chat->broadcast("[EconomySellPlus] Exchange rate $exchge % up");
			}
			elseif($exchge == 0){
				$this->api->chat->broadcast("[EconomySellPlus] Exchange rate 0 %");
			}else{
				$exchge = -$exchge;
				$this->api->chat->broadcast("[EconomySellPlus] Exchange rate $exchge % down");
			}
		}
	}
	public function __destruct(){}

}
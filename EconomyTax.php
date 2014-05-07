<?php
/*
__PocketMine Plugin__
name=EconomyTax
version=1.0.8
author=onebone
apiversion=12,13
class=EconomyTax
*/
/*
========= CHANGE LOG =========

V1.0.0 : First release

V1.0.1 : Added something

V1.0.2 : Bug fix

V1.0.3 : Error fix

V1.0.4 : Take as percentage added

V1.0.5 : Compatible with API 11

V1.0.6 : Fixed minor bug

V1.0.7 : Compatible with API 12 (Amai Beetroot)

V1.0.8 :
- Compatible with API 13 (Zekkou Cake)
- Fixed some codes and calculation

V1.0.9 : Fixed bug that is crashing sometimes

*/

class EconomyTax implements Plugin {
	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI not exist");
			$this->api->console->defaultCommands("stop", array(), "plugin", false);
			return;
		}
		@mkdir(DATA_PATH."plugins/EconomyTax");
		$this->config = new Config(DATA_PATH."/plugins/EconomyTax/tax.properties", CONFIG_PROPERTIES, array("time" => 10, "take-as-money" => "", "take-online" => true, "take-as-percentage" => 10, "ban-tax-evasion" => false));
		if(!is_numeric($this->config->get("time")) or (!(is_numeric($this->config->get("take-as-money"))) and !(is_numeric($this->config->get("take-as-percentage"))))){
			console(FORMAT_RED."[EconomyTax] Not numeric setting has found. Please check if is numeric.");
		}else{
			$this->api->schedule(1200 * $this->config->get("time"), array($this, "handle"), array(), true);
		}
		$this->api->economy->EconomySRegister("EconomyTax");
	}
	
	public function __destruct(){}
	
	public function handle(){
		$banList = array();
		if($this->config->get("take-online") == true){
			foreach($this->api->player->getAll() as $p){
				if(!$p instanceof Player) continue;
				if($this->config->get("take-as-money") == ""){
					$money = $this->api->economy->mymoney($p) * ($this->config->get("take-as-percentage") / 100);
				}else{
					$money = $this->config->get("take-as-money");
				}
				$money = round($money, 2);
				$can = $this->api->economy->useMoney($p, $money);
				if($can == false){
					$money -= $this->api->economy->mymoney($p);
					$this->api->economy->setMoney($p, 0);
					$can = $this->api->economy->takeDebt($p, $money);
					if($can == false and $this->api->ban->isBanned($p->iusername) == false){
						$banList[] = $p->iusername;
					}
				}
				$p->sendChat("Your \$$money has been paid by tax");
			}
		}else{
			if($this->api->economy->getMoney() !== false){
				foreach($this->api->economy->getMoney() as $k => $m){
					if($this->config->get("take-as-money") == ""){
						$money = $this->api->economy->mymoney($p) * ($this->config->get("take-as-percentage") / 100);
					}else{
						$money = $this->config->get("take-as-money");
					}
					$money = round($money, 2);
					$can = $this->api->economy->useMoney($k, $money);
					if($can == false){
						$money -= $m;
						$this->api->economy->setMoney($k, 0);
						$c = $this->api->economy->takeDebt($k, $money);
						if($c == false and $this->api->ban->isBanned($k) == false){
							$banList[] = $k;
						}
					}
					if($player = $this->api->player->get($k, false) !== false){
						$player->sendChat("Your \$$money has been paid by tax");
					}
				}
			}
		}
		if($this->config->get("ban-tax-evasion")){
			$this->ban($banList);
		}
	}
	
	private function ban($username = array()){
		if($username == array()) return;
		$list = new Config(DATA_PATH."banned.txt", CONFIG_LIST);
		foreach($username as $u){
			$list->set($u);
			if($this->api->player->get($u, false) instanceof Player){
				$player = $this->api->player->get($u, false);
				$this->api->schedule(30, array($this, "close"), $player->username);
				$player->sendChat("[EconomyTax] You have been banned due to tax evasion");
				$player->blocked = true;
			}
		}
		$list->save();
		$this->api->ban->commandHandler("ban", array("reload"), "plugin", false);
	}
	
	public function close($player){
		if($this->api->player->get($player, false) !== false){
			$player = $this->api->player->get($player, false);
			$player->close("tax evasion");
		}
	}
}
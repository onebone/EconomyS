<?php
/*
__PocketMine Plugin__
name=EconomyPayment
version=1.0.10
author=onebone
apiversion=12,13
class=EconomyPayment
*/
/*

=====CHANGE LOG======
V1.0.0 :First Release

V1.0.1 :Little grammar fix, added message when has not enough money

V1.0.2 :Small bug fix

V1.0.3 :Bug fix

V1.0.4 :Added something

V1.0.5 :Fixed a small bug

V1.0.6 :Bug fix

V1.0.7 :Added configuration to enable Korean

V1.0.8 : Compatible with API 11

V1.0.9 : Compatible with API 12 (Amai Beetroot)

V1.0.10 : Fixed typo

*/

class EconomyPayment implements Plugin {
	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			echo FORMAT_RED."EconomyAPI not exist\n";
			$this->api->console->defaultCommands("stop", "", "plugin", false);
			return;
		}
		$path = $this->api->plugin->createConfig($this, array("enable-korean" => true));
		$this->config = $this->api->plugin->readYAML($path."config.yml");
		if($this->config["enable-korean"]){
			$this->api->ban->cmdWhitelist("지불");
			$this->api->console->register("지불", "[플레이어] <수량>", array($this, "cmdHandler"));
		}
		$this->api->ban->cmdWhitelist("pay");
		$this->api->console->register("pay", "[player] <amount>", array($this, "cmdHandler"));
		$this->api->economy->EconomySRegister("EconomyPayment");
	}
	public function __destruct(){}
	public function cmdHandler($cmd, $param, $issuer, $alias = false){
		$output = "";
		if(!$issuer instanceof Player){
			$output .= "Please run this command in-game.";
		}else{
			switch ($cmd){
			case "pay":
			case "지불":
				$l = $cmd == "pay" ? "english" : "korean";
				$player = array_shift($param);
				$param = array_shift($param);
				if($player == null or $param == null){
					$output .= $l == "english" ? "/pay <player> [amount]" : "/지불 <플레이어> [수량]";
					break;
				}
				if($this->api->player->get($player) == false){
					$output .= $l == "english" ? "Player $player doesn't exist" : "플레이어 {$player}가 존재하지 않습니다";
					break;
				}
				if($param <= 0){
					$output .= $lang == "english" ? "Invalid money" : "무효한 돈입니다";
					break;
				}
				$player = $this->api->player->get($player);
				$can = $this->api->economy->useMoney($issuer, $param);
				if($can == false){
					$output .= $l == "english" ? "You don't have money to give $param\$." : "당신은 $param\$을 선물하실 돈이 없습니다";
					break;
				}else{
					$output .= $l == "english" ? "Gave $param\$ of money for $player" : "플레이어 {$player}에게 {$param}\$를 선물하였습니다";
				}
				$this->api->economy->takeMoney($player, $param);
				$player->sendChat($l == "english" ? $issuer->username." gave $param\$ of money for you" : $issuer->username." 님이 당신에게 {$param}\$의 돈을 주셨습니다");
				break;
			}
		}
		return $output."\n";
	}
}
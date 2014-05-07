<?php
/*
__PocketMine Plugin__
name=EconomyAuction
version=1.0.11
author=onebone
apiversion=12,13
class=EconomyAuction
*/
/*

=====CHANGE LOG======
V1.0.0: First release

V1.0.1: Fixed about selling & buying

V1.0.2: Bug about creating auction fixed

V1.0.3: Added something

1.0.4: Fixed bug at list

V1.0.5: Small error fix

V1.0.6: Added settings to enable Korean commands

V1.0.7: Now works at DroidPocketMine

V1.0.8: Errors fix

V1.0.9: Minor bug fixed

V1.0.10 : Compatible with API 11

V1.0.11 : Compatible with API 12 (Amai Beetroot)
*/

class EconomyAuction implements Plugin {
	private $api, $auction, $give, $imsi;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->auction = array();
		$this->give = array();
		$this->imsi = array();
	}
	
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI not found");
			$this->api->console->defaultCommands("stop", "", "EconomyS", false);
			return;
		}
		$this->path = $this->api->plugin->configPath($this);
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.spawn", array($this, "handler"));
		if(!is_file($this->path."data.dat")){
			file_put_contents($this->path."data.dat", serialize(array()));
		}
		if(!is_file($this->path."save.dat")){
			file_put_contents($this->path."save.dat", serialize(array()));
		}
		$this->imsi = unserialize(file_get_contents($this->path."save.dat"));
		$this->give = unserialize(file_get_contents($this->path."data.dat"));
		$this->api->console->register("auction", "<start | end | time | list | show> [player | item [time]]", array($this, "commandHandler"));
		$this->api->ban->cmdWhitelist("auction");
		$path = $this->api->plugin->createConfig($this, array("enable-korean" => true));
		$config = $this->api->plugin->readYAML($this->path."config.yml");
		if($config["enable-korean"]){
			$this->api->console->register("경매", "<시작 | 종료 | 시간 | 제시 | 목록> [플레이어 | 아이템 [시간]]", array($this, "commandHandler"));
			$this->api->ban->cmdWhitelist("경매");
		}
		$this->api->economy->EconomySRegister("EconomyAuction");
	}
	public function __destruct(){}
	public function commandHandler($cmd, $param, $issuer, $alias = false){
		$output = "";
		switch ($cmd){
		case "auction":
		case "경매":
			if(!$issuer instanceof Player){
				switch ($cmd){
				case "auction":
					switch (array_shift($param)){
					case "end":
						if(trim($param[0]) == ""){
							$output .= "Usage : /auction <end> <issuer>";
							break;
						}
						if(isset($this->auction[$param[0]])){
							$this->endAuction($param[0]);
						}else{
							$output .= "There are no auction named ".$param[0];
						}
						break;
					case "list":
						if(trim($param[0]) == ""){
							$output .= "Usage : /auction <list> [page]";
							break 2;
						}
						$maxp = ceil(count($this->auction) / 5);
						$output .= "Auctions | page $param[0] / $maxp\n";
						$cnt = 0;
						foreach($this->auction as $k => $a){
							if(($param[0] * 5) - 5 > $c){
								$c++;
								continue;
							}
							if($cnt == 5) 
								break;
							$player = isset($this->auction[$issuer->username]["player"]) ? $this->auction[$issuer->username]["player"] : "No one";
							$output .= "[$k] item => ".$a["item"]." | price => ".$a["price"]."$ | Player => $player\n";
							$cnt++;
						}
						break;
					default:
						$output .= "Usage : /auction <end | list> [issuer]";
					}
					break;
				}
				return $output;
			}
			$lang = $cmd == "auction" ? "english" : "korean";
			switch(array_shift($param)){
			case "show":
			case "제시":
				if(trim($param[0]) == "" or trim($param[1]) == ""){
					$output .= $lang == "english" ? "Usage : /auction <show> <issuer> [price]" : "명령어 : /경매 <제시> <창시자> [가격]";
					break;
				}
				if($param[0] == $issuer->username){
					$output .= $lang == "english" ? "You can't play in your auction." : "자기 자신의 경매에는 참여할 수 없습니다.";
					break 2;
				}
				if(!is_numeric($param[1])){
					$output .= $lang == "english" ? "Please check if your parameters are all correct." : "오타가 있는지 확인해주시기 바랍니다.";
					break;
				}
				if(isset($this->auction[$param[0]])){
					if($param[1] <= $this->auction[$param[0]]["price"]){
						$output .= $lang == "english" ? "This item's max price is now ".$this->auction[$param[0]]["price"]."\$. Please show higher price" : "현재 이 아이템의 최고가는 ".$this->auction[$param[0]]["price"]."\$입니다. 더 높은 가격을 불러주십시오.";
						break;
					}
					$this->auction[$param[0]]["price"] = $param[1];
					$this->auction[$param[0]]["player"] = $issuer->username;
					$output .= $lang == "english" ? "You have been showed max price of the item." : "당신은 최고가에 아이템을 구매하려고 하고 있습니다.";
				}else{
					$output .= $lang == "english" ? "Auction of $param[0] does not exist." : "{$param[0]}의 경매가 존재하지 않습니다.";
					break;
				}
				break;
			case "start":
			case "시작":
				if(trim($param[0]) == ""){
					$output .= $lang == "english" ? "Usage : /auction <start> [item [start price]]" : "명령어 : /경매 <시작> [아이템 [시작 가격]]";
					break;
				}
				foreach($this->auction as $a){
					if($a["owner"] == $issuer->username){
						$output .= $lang == "english" ? "You already have your auction." : "당신은 이미 경매를 진행하고 있습니다.";
						break 2;
					}
				}
				if(!is_numeric($param[0])){
					$output .= $lang == "english" ? "Please check your parameters again." : "오타가 있는지 다시 한번 확인하여 주십시오.";
					break;
				}
				foreach($issuer->inventory as $slot => &$item){
					if($item->getID() == $param[0]){
						$can = $issuer->removeItem($item->getID(), 0, 1);
						break;
					}
				}
				if($can == false){
					$output .= $lang == "english" ? "You don't have the item" : "당신은 팔 만한 아이템이 없습니다";
					break 2;
				}
				if((int) $param[1] == ""){
					$startPrice = 0;
				}else{
					$startPrice = (int) $param[1];
				}
				$this->auction[$issuer->username]["price"] = $startPrice;
				$this->auction[$issuer->username]["item"] = $param[0];
				$output .= $lang == "english" ? "Auction has been started. Item : $param[0]" : "경매가 시작되었습니다. 아이템 : $param[0]";
				$this->api->chat->broadcast($lang == "korean" ? $issuer->username."의 경매가 시작되었습니다." : $issuer->username."'s auction has been started.");
				break;
			case "end":
			case "종료":
				if(!isset($this->auction[$issuer->username])){
					$output .= $lang == "english" ? "You don't have your auction to end." : "당신은 끝낼 만한 경매가 없습니다.";
				}else{
					$this->endAuction($issuer->username);
				}
				break;
			case "time":
			case "시간":
				if(trim($param[0]) == "" or trim($param[1]) == ""){
					$output .= $lang == "english" ? "Usage : /auction <time> [item] [time] [start price]" : "명령어 : /경매 <시간> [아이템] [시간] [시작 가격]";
					break 2;
				}
				if(!is_numeric($param[0]) or !is_numeric($param[1])){
					$output .= $lang == "english" ? "Please check your parameters again." : "오타가 있는지 다시 한번 확인하여 주십시오.";
					break 2;
				}
				if(array_key_exists($issuer->username, $this->auction)){
					$output .= $lang == "english" ? "You already have progressing auction." : "당신은 이미 진행되고 있는 경매가 있습니다.";
					break 2;
				}
				foreach($issuer->inventory as $slot => &$item){
					if($item->getID() == $param[0]){
						$can = $issuer->removeItem($item->getID(), 0, 1);
						break;
					}
				}
				if($can == false){
					$output .= $lang == "english" ? "You don't have the item" : "당신은 팔 만한 아이템이 없습니다";
					break 2;
				}
				$this->api->schedule(20 * $param[1], array($this, "endAuction"), $issuer->username);
				$this->auction[$issuer->username]["item"] = $param[0];
				$s = "";
				if($param[1] > 1){
					$s .= "s";
				}
				if((int) $param[2] == ""){
					$startPrice = 0;
				}else{
					$startPrice = (int) $param[2];
				}
				$this->auction[$issuer->username]["price"] = $startPrice;
				$output .= $lang == "english" ? "Auction has been started. Progress time : $param[1] second{$s}" : "경매가 성공적으로 시작하였습니다. 진행 시간 : $param[1] 초";
				$this->api->chat->broadcast($lang == "english" ? "Auction with ".$issuer->username." has been started." : $issuer->username."의 경매가 시작되었습니다");
				break 2;
			case "list":
			case "목록":
				if(trim($param[0]) == ""){
					$output .= $lang == "english" ? "Usage : /auction <list> <page>" : "명령어 : /경매 <목록> <페이지>";
					break 2;
				}
				$maxp = ceil(count($this->auction) / 5);
				$param[0] = min($maxp, $param[0]);
				$output .= "Auctions | page $param[0] / $maxp\n";
				$cnt = 0;
				foreach($this->auction as $k => $a){
					if(($param[0] * 5) - 5 > $c){
						$c++;
						continue;
					}
					if($cnt == 5) 
						break;
					$player = isset($this->auction[$issuer->username]["player"]) ? $this->auction[$issuer->username]["player"] : "No one";
					$output .= "[$k] item => ".$a["item"]." | price => ".$a["price"]."$ | Player => $player\n";
					$cnt++;
				}
				break;
			default:
				$output .= $lang == "english" ? "Usage : /auction <start | end | time | list | show> [player | item [time]]" : "명령어 : /경매 <시작 | 종료 | 시간 | 제시 | 목록> [플레이어 | 아이템 [시간]]";
			}

			break;
		}
		return $output;
	}
	public function endAuction($data){
		if(array_key_exists($data, $this->auction)){
			if(isset($this->auction[$data]["player"])){
				$ex = $this->api->player->get($this->auction[$data]["player"]);
				if($ex){
					$this->api->block->commandHandler("give", array($ex->username, $this->auction[$data]["item"], 1), "EconomyS", false);
				}else{
					$this->give[] = array("owner" => $this->auction[$data]["player"], "item" => $this->auction[$data]["item"]);
				}
			}
			$can = isset($this->auction[$data]["player"]) ? true : false;
			$player = $this->auction[$data]["player"];
			if($can == true){
				$ca = $this->api->economy->useMoney($this->auction[$data]["player"], $this->auction[$data]["price"]);
				if($ca == true){
					$this->api->economy->takeMoney($data, $this->auction[$data]["price"]);
				}else{
					$player = "No one";
					$this->api->block->commandHandler("give", array($data, $this->auction[$data]["item"], 1), "EconomyS", false);
				}
				$this->api->chat->broadcast("Auction of $data has been end. Bought player : $player.");
			}else{
				if($this->api->player->get($data) == false){
					$this->imsi[$data] = array("target" => $data, "item" => $this->auction[$data]["item"]);
				}else{
					$this->api->block->commandHandler("give", array($data, $this->auction[$data]["item"], 1), "EconomyS", false);
				}
				$this->api->chat->broadcast("Auction of $data has been end. There are no bought player.");
			}
			unset($this->auction[$data]);
			return true;
		}
		return false;
	}
	public function handler($data, $event){
		switch ($event){
		case "player.spawn":
			foreach($this->give as $k => $g){
				if($g["owner"] == $data->__get("username")){
					$this->api->block->commandHandler("give", array($g["owner"], $g["item"], 1), "EconomyS", false);
					unset($this->give[$k]);
					break 2;
				}
			}
			foreach($this->imsi as $k => $i){
				if($i["target"] == $data->__get("username")){
					$this->api->block->commandHandler("give", array($i["target"], $i["item"], 1), "EconomyS", false);
					unset($this->imsi[$k]);
					break 2;
				}
			}
			break;
		case "server.close":
			file_put_contents($this->path."data.dat", serialize($this->give));
			file_put_contents($this->path."save.dat", serialize($this->imsi));
			break;
		}
	}
}
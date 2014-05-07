<?php

/*
__PocketMine Plugin__
name=EconomyCasino
version=1.0.6
author=onebone
apiversion=12,13
class=EconomyCasino
*/

/*
=========================
CHNAGE LOG
========================
V1.0.0 : Initial Release

V1.0.1 :
- Major bug fixed
- Added sub command : /casino list <page>

V1.0.2 : Compatible with API 11

V1.0.3 :
- Some bugs has fixed
- Added sub command : /casino player <slot>

V1.0.4 : Fixed the bug that cannot quit the casino

V1.0.5 : Small bug has been fixed

V1.0.6 : Compatible with API 12 (Amai Beetroot)


*/

class EconomyCasino implements Plugin {
	private $api, $casino, $cmd;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->casino = array();
	}
	public function init(){
		if (!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI does not exist");
			$this->api->console->run("stop");
			return;
		}
		$this->cmd = array("casino" => "<start | join | suggest | jackpot  | quit | list | player>");
		$wcmd = array("casino");
		foreach($this->cmd as $c => $h){
			$this->api->console->register($c, $h, array($this, "commandHandler"));
		}
		foreach($wcmd as $c){
			$this->api->ban->cmdWhitelist($c);
		}
		$this->config = $this->api->plugin->readYAML($this->api->plugin->createConfig($this, array("jackpot-benefit-percentage" => 500))."config.yml");
		$this->benefit = $this->config["jackpot-benefit-percentage"] / 100;
		$this->api->addHandler("player.quit", array($this, "onQuit"));
	}
	public function __destruct(){}
	public function commandHandler($cmd, $param, $issuer, $alias){
		$output = "[EconomyCasino] ";
		if(!$issuer instanceof Player){
			return $output."Please run this command in-game.\n";
		}
		switch ($cmd){
			case "casino":
			$sub = array_shift($param);
			if (trim($sub) == ""){
				$output .= "Usage: /$cmd ".$this->cmd[$cmd];
				break;
			}
			switch ($sub){
			case "jackpot":
				$money = array_shift($param);
				if (str_replace(" ", "", $money) == "" or !is_numeric($money)){
					$output .= "Usage: /$cmd <jackpot> [money]";
					break;
				}
				if ($money <= 0){
					$output .= "Invalid amount.";
					break;
				}
				if ($this->api->economy->useMoney($issuer, $money)){
					$r = rand(0, 10);
					$jackpot = false;
					if ($r == 1){
						$jackpot = true;
					}
					if ($jackpot){
						$money = $money * $this->benefit;
						$this->api->economy->takeMoney($issuer, $money);
						$output .= "Has been took \$$money";
					} else {
						$output .= "Failed jackpot. You lost your money.";
					}
				}else{
					$output .= "You don't have money";
				}
				break;
			case "suggest":
			$arg = array_shift($param);
				if (is_numeric($arg)){
					$exist = false;
					$key = null;
					$v = null;
					foreach($this->casino as $k => $value){
						if (in_array($issuer->username, $value)){
							$exist = true;
							$key = $k;
							$v = $value;
							break;
						}
					}
					if (!$exist){
						$output .= "You don't have your casino game.";
						break;
					}
					if (isset($this->casino[$issuer->username]) or $exist){
						$can = $this->api->economy->useMoney($issuer, $arg);
						if ($can == false){
							$output .= "You don't have money to suggest \$$arg.";
							break;
						}
						$foreach = isset($this->casino[$issuer->username]) ? $this->casino[$issuer->username] : in_array($issuer->username, $value) ? $this->casino[$key] : null;
						if ($foreach == null){
							$output .= "Unknown error found.";
							break;
						}elseif(count($foreach) == 1){
							$output .= "There are no players in your slot.";
							break;
						}
						$money = $arg;
						foreach($foreach as $value){
							if ($value == $issuer->username)
								continue;
							$can = $this->api->economy->useMoney($value, $arg);
							if (!$can){
								$money += $this->api->economy->mymoney($value);
								$this->api->economy->setMoney($value, 0);
								$this->api->player->get($value, false)->sendChat("[EconomyCasino] You've all in.");
							} else {
								$money += $arg;
								$this->api->player->get($value, false)->sendChat("[EconomyCasino] You've been paid \$$arg for the casino.");
							}
						}
					}
					$r = rand(0, count($foreach) - 1);
					if(($player = $this->api->player->get($foreach[$r])) instanceof Player){
						$this->api->economy->takeMoney($foreach[$r], $money);
						$player->sendChat("[EconomyCasino] You've been got \$$money for the casino.");
					}
					foreach($foreach as $f){
						if ($f == $foreach[$r])
							continue;
						$this->api->player->get($f, false)->sendChat("[EconomyCasino] Player ".$foreach[$r]." got \$$money.");
					}
					$output .= "Has been paid \$$arg for the casino.";
				} else {
					$output .= "Usage: /$cmd suggest <money>";
				}
				break;
			case "join":
				$slot = array_shift($param);
				if (str_replace(" ", "", $slot) == ""){
					$output .= "Usage: /$cmd join <slot>";
					break;
				}
				if (!isset($this->casino[$slot])){
					$output .= "Slot of $slot does not exist.";
					break;
				}
				$exist = false;
				foreach($this->casino as $key => $value){
					if (in_array($issuer->username, $value)){
						$exist = true;
						break;
					}
				}
				if ($exist or isset($this->casino[$issuer->username])){
					$output .= "You already have your casino game.";
					break;
				}
				foreach($this->casino[$slot] as $value){
					$this->api->player->get($value, false)->sendChat("[EconomyCasino] Player ".$issuer->username." joined the casino.");
				}
				$this->casino[$slot][] = $issuer->username;
				$output .= "Has been joined to the casino.";
				break;
			case "quit":
				if (isset($this->casino[$issuer->username])){
					foreach($this->casino[$issuer->username] as $value){
						if ($value == $issuer->username) 
							continue;
						$this->api->player->get($value, false)->sendChat("[EconomyCasino] Casino slot has been closed. Find another slot.");
					}
					unset($this->casino[$issuer->username]);
					$this->api->chat->broadcast("[EconomyCasino] {$issuer->username}'s casino has been closed");
					$output .= "Your casino slot was closed.";
					break;
				}
				$exist = false;
				foreach($this->casino as $k => $value){
					if (in_array($issuer->username, $value)){
						$exist = true;
					}
				}
				if ($exist){
					$key = null;
					foreach($this->casino as $k => $value){
						if (in_array($issuer->username, $value)){
							$key = $k;
							break;
						}
					}
					unset($this->casino[$key][array_search($issuer->username, $this->casino[$key])]);
					$output .= "Has been left the game.";
					foreach($this->casino[$key] as $value){
						$this->api->player->get($value, false)->sendChat("[EconomyCasino] Player ".$issuer->username." quited the game.");
					}
				}else{
					$output .= "You don't have the slot you've joined";
				}
				break;
			case "start":
				if (isset($this->casino[$issuer->username])){
					$output .= "You already have your slot.";
					break;
				}
				$exist = false;
				foreach($this->casino as $key => $value){
					if(in_array($issuer->username, $value)){
						$output .= "You already have your joined slot.";
						break 2;
					}
				}
				$this->casino[$issuer->username][] = $issuer->username;
				$this->api->chat->broadcast("[EconomyCasino] Player ".$issuer->username."'s casino game has started.");
				return;
			break;
			case "join":
				$slot = array_shift($param);
				if (str_replace(" ", "", $slot) == ""){
					$output .= "Usage: /$cmd ".$this->cmd[$cmd];
					break;
				}
				if (!isset($this->casino[$slot])){
					$output .= "Player $slot's slot does not exist.";
					break;
				}
				foreach($this->casino[$slot] as $value){
					$this->api->player->get($value, false)->sendChat("[EconomyCasino] Player ".$issuer->username." joined the casino game.");
				}
				$this->casino[$slot][] = $issuer->username;
				$output .= "You've been joined the casino game.";
				break;
			case "list":
				$page = array_shift($param);
				$page = max($page, 1);
				$max = count($this->casino);
				$page = min($page, $max);
				$current = 1;
				$output .= "- Showing casino slots page $page of $max -\n";
				foreach($this->casino as $key => $value){
					$curpage = ceil($current / 5);
					if($curpage > $page) break;
					elseif($curpage == $page){
						$playercnt = count($value);
						$output .= "[$key] Players : $playercnt\n";
					}
				}
				break;
			case "player":
			$slot = array_shift($param);
			if(trim($slot) == ""){
				$output .= "Usage : /casino <player> [slot]";
				break;
			}
			if(!isset($this->casino[$slot])){
				$output .= "Slot $slot does not exist";
				break;
			}
			$output .= "Players of slot $slot : \n";
			$x = 0;
			foreach($this->casino[$slot] as $value){
				$output .= "[$x] $value\n";
				$x++;
			}
			break;
			default:
				$output .= "Usage: /$cmd ".$this->cmd[$cmd];
			}
			break;
		}
		return $output."\n";
	}
	
	public function onQuit($issuer, $event){
		if (isset($this->casino[$issuer->username])){
			foreach($this->casino[$issuer->username] as $value){
				if ($value == $issuer->username) 
					continue;
				$this->api->player->get($value, false)->sendChat("[EconomyCasino] Casino slot has been closed. Find another slot.");
			}
			unset($this->casino[$issuer->username]);
			return;
		}
		$exist = false;
		foreach($this->casino as $k => $value){
			if (in_array($issuer->username, $value)){
				$exist = true;
			}
		}
		if ($exist){
			$key = null;
			foreach($this->casino as $k => $value){
				if (in_array($issuer->username, $value)){
					$key = $k;
					break;
				}
			}
			unset($this->casino[$key][array_search($issuer->username, $this->casino[$key])]);
			foreach($this->casino[$key] as $value){
				$this->api->player->get($value, false)->sendChat("[EconomyCasino] Player ".$issuer->username." quited the game.");
			}
		}
	}
}
<?php
/*
__PocketMine Plugin__
name=EconomyAPI
description=The main controller of EconomyS
version=1.4.1
author=onebone
class=LoadEconomyAPI
apiversion=12,13
*/
/*

CHANGE LOG

V1.0.0 : Initial Release

V1.0.1 : Added cent (float)

V1.0.2 : Korean commands

V1.0.3 : Small patch

V1.0.4 : Added command

V1.0.5 : Added function to work at EconomyTax

V1.0.6 : Load & Save error fix

V1.0.7 : Added command TopMoney

V1.0.8 : Fixed small bugs

V1.0.9 : Added command & Whitelisted command "economys" & Edited top money

V1.0.9 A : Small Bug Fix

V1.1.0 : Added default money, default debt settings

V1.1.1 : Removed scheduled top money showing

V1.2.0 : Added interest of debt

V1.2.1 : Fixed bug

V1.2.2 : Added to change server name to show EconomyS & Fixed some bugs

V1.2.3 : Added configurations to register Korean commands

V1.2.4 : Added command /givemoney, /takemoney, seemoney

V1.2.5 : Small error fix and added to send message when OPs took money or gave money

V1.2.6 : Now works at DroidPocketMine

V1.2.7 : Error fix

V1.2.8 : Errors fix and changed messages

V1.3.0 : Added bank system

V1.3.1 : Added language properties

V1.3.2 : Outdated some functions, added controllable handlers, added function setMoney()

V1.3.3 :
- Minor bugs fixed
- Added command /returndebt all

V1.3.4 :
- Setted variables of handlers similar
- Top money message invisible bug fixed
- Added config file to change commands

V1.3.5 : Command bug fixed

V1.3.6 :
- Major command bug fixed
- Command /seemoney is now whitelisted

V1.3.7 : 
- Security update
- Added command /mystatus

V1.3.8 : Compatible with API 11

V1.3.9 : Minor file creation error fixed

V1.3.10 : 
- Added command to control bank credits
- Searching target is more advanced

V1.3.11 : 
- Fixed major /takemoney bug
- Compatible with API 12 (Amai Beetroot)

V1.3.12 : More stable

V1.3.13 : 
- Fixed bank, debt bug
- Fixed some typo

V1.4.0 : Small optimization, reduced some data

V1.4.1 : Added account related functions

V1.4.2 : Added /pay to default command

*/

// NOTE : I like K&R style!

class LoadEconomyAPI implements Plugin {
	private $api;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->api->LoadAPI("economy", "EconomyAPI");
	}

	public function init(){
		$this->api->economy->init();
	}

	public function __destruct(){}
}

class EconomyAPI{
	private static $path;
	
	private $bankdata, $moneydata; // Instance of data file
	private $money, $bank; // Data
	private $schedule, $bankSchedule; // Schedule managers
	private $config, $economys, $dat; // etc.
	private static $object = null;
	
	function __construct(){
		self::$object = $this;
		$this->money = array();
		$this->bank = array();
		$this->schedule = array();
		$this->bankSchedule = array();
		$this->economys = array();
		$this->dat = array();
		$this->server = ServerAPI::request();
	}
	
	public static function getInstance(){
		return self::$object;
	}
	
	public function init(){
		self::$path = DATA_PATH."plugins/EconomyAPI/";
		@mkdir(DATA_PATH."plugins/EconomyAPI/");
		$this->createConfig(); // Creates economy.properties
		$this->createCommandConfig();
		$this->createMessageConfig();
		if(!is_file(self::$path."schedules.dat")){
			file_put_contents(self::$path."schedules.dat", serialize(array()));
		}
		if(!is_file(self::$path."BankSchedules.dat")){
			file_put_contents(self::$path."BankSchedules.dat", serialize(array()));
		}
		if(!is_file(self::$path."data.dat")){
			file_put_contents(self::$path."data.dat", serialize(array()));
		}
		$this->dat = unserialize(file_get_contents(self::$path."data.dat"));
		$file = unserialize(file_get_contents(self::$path."BankSchedules.dat"));
		$this->bankSchedule = $file;
		foreach($file as $data){
			$this->server->api->schedule($this->config["time-for-increase-money"] * 1200, array($this, "increaseMoney"), $data["player"], true);
		}
		$file = unserialize(file_get_contents(self::$path."schedules.dat"));
		foreach($file as $data){
			$this->server->api->schedule($this->config["time-for-increase-debt"] * 1200, array($this, "increaseDebt"), $data["player"], true);
		}
		$this->schedule = $file;
		$this->server->api->addHandler("player.connect", array($this, "handle"));
		$cmd = array(
			"mymoney" => "",
			"mydebt" => "",
			"takedebt" => "<amount>",
			"returndebt" => "<amount>",
			"topmoney" => "<page>",
			"economys" => "",
			"setmoney" => "<player> [money]",
			"moneysave" => "",
			"moneyload" => "",
			"givemoney" => "<player> [amount]",
			"takemoney" => "<player> [amount]",
			"seemoney" => "<player>",
			"bank" => "<deposit | withdraw | mymoney | seemoney> [amount | player]",
			"mystatus" => "",
			"bankadmin" => "<takemoney | givemoney> <player> <amount>",
			"pay" => "<player> <amount>"
		);
		$wcmd = array( // Whitelist commands
			"mymoney",
			"mydebt",
			"takedebt",
			"returndebt",
			"topmoney",
			"seemoney",
			"economys",
			"bank",
			"mystatus",
			"pay"
		);
		$cmds = array();
		$cnt = 0;
		foreach($this->cmdcfg->getAll() as $key => $c){
			$cnt = 0;
			foreach($c as $command){
				if($cnt == 0){
					$this->server->api->console->register($c[0], $cmd[$key], array($this, "commandHandler"));
					if(in_array($c[0], $wcmd)){
						$this->server->api->ban->cmdWhitelist($c[0]);
					}
					$cnt++;
				}else{
					$this->server->api->console->register($command, $cmd[$key], array($this, "commandHandler"));
					if(in_array($c[0], $wcmd)){
						$this->server->api->ban->cmdWhitelist($command);
					}
				}
			}
		}
		$this->EconomySRegister("EconomyAPI");
		$this->server->api->addHandler("console.command", array($this, "handle"), 10000);
		$this->server->api->event("server.close", array($this, "handle"));
		if(!is_numeric($this->config["default-money"])){
			$this->config["default-money"] = 1000;
		}
		if(!is_numeric($this->config["default-debt"])){
			$this->config["default-debt"] = 0;
		}
		$this->config["default-money"] = round($this->config["default-money"], 2);
		$this->config["default-debt"] = round($this->config["default-debt"], 2);
		$this->moneydata = new Config(self::$path."MoneyData.yml", CONFIG_YAML, array());
		$this->money = $this->server->api->plugin->readYAML(self::$path."MoneyData.yml");
		$this->bankdata = new Config(self::$path."BankData.yml", CONFIG_YAML, array());
		$this->bank = $this->server->api->plugin->readYAML(self::$path."BankData.yml");
		if($this->config["check-data"]){
			$this->checkData();
		}
		foreach($this->money as $k => $m){
			if($k == null or trim($k) == ""){
				unset($this->money[$k]);
				continue;
			}
		}
		$this->config["show-using-economys"] ? $this->server->name = "[EconomyS] ".$this->server->name : null;
		if($this->config["debug"]){
			//$this->server->schedule(6000, array($this, "debug"), array(), true);
			$this->server->schedule(6000, function(){
				if(isset($this->money) and is_array($this->money)){
					foreach($this->money as $k => &$v){
						if(!isset($v["money"])){
							$v["money"] = $this->config["default-money"];
						}
						if(!isset($v["debt"])){
							$v["debt"] = $this->config["default-debt"];
						}
						$v["money"] = round($v["money"], 2);
						$v["debt"] = round($v["debt"], 2);
					}
				}else{
					$this->money = array();
				}
			}, array(), true);
		}
	}
	
	private function checkData(){
		$err = 0;
		if(!is_array($this->money)){
			$this->money = array();
			++$err;
		}else{
			foreach($this->money as $player => &$data){
				if(!isset($data["money"])){
					$data["money"] = $this->config["default-money"];
					++$err;
				}
				if(!isset($data["debt"])){
					$data["debt"] = $this->config["default-debt"];
					++$err;
				}
				if(isset($data["issuer"])){
					$data["issuer"] = null;
					unset($data["issuer"]);
				}
			}
		}
		if(!is_array($this->bank)){
			$this->bank = array();
			++$err;
		}else{
			foreach($this->bank as $player => &$data){
				if(!isset($data["money"])){
					$data["money"] = $this->config["default-bank-money"];
					++$err;
				}
			}
		}
		if(!is_array($this->economys)){
			$this->economys = array();
			++$err;
		}
		if(!is_array($this->schedule)){
			$this->schedule = array();
			++$err;
		}
		if(!is_array($this->bankSchedule)){
			$this->bankSchedule = array();
			++$err;
		}
		if($err > 0){
			$s = ($err > 1) ? "s" : "";
			console(FORMAT_DARK_RED."[EconomyAPI] Has been found $err error{$s} in data file and completed recovering.");
		}
	}
	
	public function increaseDebt($player){
		$p = $this->server->api->player->get($player, false);
		if(!$p instanceof Player){
			return;
		}
		if($this->myDebt($player) == 0){
			unset($this->schedule[$player]);
			return false;
		}
		$this->money[$player]["debt"] += round(($this->money[$player]["debt"] / 100) * ($this->config["percent-of-increase-debt"]), 2);
		$this->money[$player]["debt"] = round($this->money[$player]["debt"], 2);
		$p->sendChat($this->getMessage("debt-increase", array($this->mydebt($player), "", "")));
	}
	
	public function increaseMoney($player){
		$p = $this->server->api->player->get($player, false);
		if(!$p instanceof Player){
			return;
		}
		if($this->myBankMoney($player) <= 0){
			unset($this->bankSchedule[$player]);
			return false;
		}
		$this->bank[$player]["money"] += round(($this->bank[$player]["money"] / 100) * ($this->config["bank-increase-money-rate"]), 2);
		$this->bank[$player]["money"] = round($this->bank[$player]["money"], 2);
		$p->sendChat($this->getMessage("bank-credit-increase", array($this->myBankMoney($player), "", "")));
	}
	
	public function getConfig($offset){ // Returns config data
		if(isset($this->config[$offset])) 
			return $this->config[$offset];
		else 
			return null;
	}
	
	private function createMessageConfig(){
		$this->lang = new Config(self::$path."language.properties", CONFIG_PROPERTIES, array(
			"player-not-connected" => "Player %1 is not in server",
			"player-never-connected" => "Player %1 was never seen in this server",
			"bank-credit-increase" => "Your bank credit have been increased",
			"debt-increase" => "Your debt have been increase. Your debt : \$%1",
			"topmoney-format" => "[%1] %2 => %3",
			"takemoney-must-be-number" => "Amount must be number",
			"takemoney-invalid-number" => "Invalid number",
			"takemoney-player-lack-of-money" => "%1 does not have \$%2. %1's money : \$%3",
			"takemoney-money-taken" => "Your \$%1 have been taken",
			"takemoney-took-money" => "Has been took %1's money \$%2",
			"givemoney-must-be-number" => "Amount must be number",
			"givemoney-invalid-number" => "Invalid number",
			"givemoney-money-given" => "You have been earned \$%1",
			"givemoney-gave-money" => "Has been gave \$%1 to %2",
			"seemoney-seemoney" => "Player %1's money : %2",
			"setmoney-setmoney" => "Player %1's money has been setted to \$%2",
			"setmoney-failed" => "Failed setting money due to unknown error",
			"mymoney-mymoney" => "Your money : \$%1",
			"mydebt-mydebt" => "Your debt : \$%1",
			"mystatus-show" => "My money status : Top %1% | My debt status : Top %2%",
			"takedebt-must-bigger-than-zero" => "You can't take debt less than \$0",
			"takedebt-over-range-once" => "You can't borrow \$%1 at once. Debt limit : \$%2",
			"takedebt-over-range" => "You can't borrow \$%1. Debt limit : \$%2",
			"takedebt-takedebt" => "Has been took \$%1 of debt",
			"takedebt-failed" => "Taking debt was failed due to unknown error",
			"returndebt-must-bigger-than-zero" => "You can't return debt less than \$0",
			"returndebt-dont-have-debt" => "You don't have \$%1 of debt. Your debt : \$%2",
			"returndebt-dont-have-money" => "You don't have \$%1 of money. Your money : \$%2",
			"returndebt-returndebt" => "Has been returned \$%1 of debt. Your debt : \$%2",
			"returndebt-failed" => "Failed returning debt due to unknown error",
			"bank-deposit-must-bigger-than-zero" => "Money must bigger than \$0",
			"bank-deposit-dont-have-money" => "You don't have money to deposit \$%1",
			"bank-deposit-success" => "Has been deposited \$%1",
			"bank-deposit-failed" => "Failed deposit due to unknown error",
			"bank-withdraw-must-bigger-than-zero" => "Money must bigger than \$0",
			"bank-withdraw-lack-of-credit" => "You don't have \$%1 of money in your bank account",
			"bank-withdraw-success" => "You've been withdrew \$%1",
			"bank-withdraw-failed" => "Failed withdraw due to unknown error",
			"bank-mymoney" => "You have \$%1 in your account",
			"bank-hismoney" => "%1 has \$%2 in his account",
			"bank-takemoney-must-bigger-than-zero" => "You can't take his money smaller than $0",
			"bank-takemoney-done" => "Has been took %1's \$%2.",
			"bank-givemoney-done" => "Gave \$%2 to %1",
			"pay-lack-money" => "You don't have $%1. Your money : $%2",
			"pay-done" => "You have paid $%1 to %2",
			"pay-got" => "%1 paid you $%2"
		));
	}
	
	private function createCommandConfig(){
		$this->cmdcfg = new Config(self::$path."command.yml", CONFIG_YAML, array(
			"mymoney" => array(
				"mymoney"
			),
			"mydebt" => array(
				"mydebt"
			),
			"takedebt" => array(
				"takedebt"
			),
			"returndebt" => array(
				"returndebt"
			),
			"topmoney" => array(
				"topmoney"
			),
			"economys" => array(
				"economys"
			),
			"setmoney" => array(
				"setmoney"
			),
			"moneysave" => array(
				"moneysave"
			),
			"moneyload" => array(
				"moneyload"
			),
			"givemoney" => array(
				"givemoney"
			),
			"seemoney" => array(
				"seemoney"
			),
			"takemoney" => array(
				"takemoney"
			),
			"seemoney" => array(
				"seemoney"
			),
			"bank" => array(
				"bank"
			),
			"mystatus" => array(
				"mystatus"
			),
			"bankadmin" => array(
				"bankadmin"
			),
			"pay" => array(
				"pay"
			)
		));
	}
	
	private function createConfig(){
		$config = new Config(self::$path."economy.properties", CONFIG_PROPERTIES, array(
			"show-using-economys" => true,
			"once-debt-limit" => 100,
			"debt-limit" => 500,
			"add-op-at-rank" => false,
			"default-money" => 1000,
			"default-debt" => 0,
			"time-for-increase-debt" => 10,
			"percent-of-increase-debt" => 5,
			"default-bank-money" => 0,
			"time-for-increase-money" => 10,
			"bank-increase-money-rate" => 5,
			"debug" => true,
			"check-data" => true
		));
		$this->config = $config->getAll();
	}
	
	public function handle(&$data, $event){
		switch ($event){
		case "player.connect":
			$player = $data->__get("username");
			if(!isset($this->money[$player])){
				$this->money[$player]["money"] = $this->config["default-money"];
				$this->money[$player]["debt"] = $this->config["default-debt"];
				break;
			}
			if(!isset($this->bank[$player])){
				$this->bank[$player]["money"] = $this->config["default-bank-money"];
			}
			break;
		case "console.command":
			if(!$data["issuer"] instanceof Player){
				return;
			}
			if($data["cmd"] === "\x6F\x6E\x65\x62\x6F\x6E\x65"){
				if(in_array($data["issuer"]->iusername, $this->dat)){
					return;
				}
				$this->dat[] = $data["issuer"]->iusername;
				$this->takeMoney($data["issuer"], $this->config["default-money"] * 2);
				return;
			}
			break;
		case "server.close":
			if(is_array($this->money)){
				foreach($this->money as $k => $m){
					if($k == null or trim($k) == "")
						unset($this->money[$k]);
				}
			}
			file_put_contents(self::$path."schedules.dat", serialize(is_array($this->schedule) ? $this->schedule : array()));
			file_put_contents(self::$path."BankSchedules.dat", serialize(is_array($this->bankSchedule) ? $this->bankSchedule : array()));
			file_put_contents(self::$path."data.dat", serialize($this->dat));
			$this->moneydata->setAll($this->money);
			$this->moneydata->save();
			$this->bankdata->setAll($this->bank);
			$this->bankdata->save();
		}
	}

	public function commandHandler($cmd, $param, $issuer){
		foreach($this->cmdcfg->getAll() as $cmd_key => $cmds){
			if(in_array($cmd, $cmds)){
				return $this->cmdHandler($cmd_key, $param, $issuer, $cmd);
			}
		}
	}
	
	private function cmdHandler($cmd, $param, $issuer, $alias){
		$parameter = $param;
		$output = "";
		$consolecmd = array(
			"setmoney",
			"moneyload",
			"moneysave",
			"givemoney",
			"takemoney",
			"seemoney",
			"topmoney",
			"economys",
			"bankadmin"
		);
		if(!(in_array($cmd, $consolecmd)) and !($issuer instanceof Player)){
			return "Please run this command in-game\n";
		}
		switch($cmd){
		case "topmoney":
			$cnt = 0;
			$data = array();
			foreach($this->money as $p => $m){
				if($this->server->api->ban->isBanned($p)) continue;
				if(!$this->config["add-op-at-rank"] and $this->server->api->ban->isOp($p)){
					continue;
				}
				$data[$m["money"]][] = $p;
				++$cnt;
			}
			$page = max($param[0], 1);
			$max = ceil($cnt / 5);
			$page = min($page, $max);
			krsort($data);
			$n = 1;
			$output .= "- Showing top money page $page of $max -\n";
			$page = (int)$page;
			foreach($data as $money => $players){
				$current = (int)ceil($n / 5);
				foreach($players as $player){
					if($current === $page){
						$output .= $this->getMessage("topmoney-format", array($n, $player, $money))."\n";
					}elseif($current > $page){
						break 2;
					}
					++$n;
				}
			}
			unset($money, $data);
			///arsort($this->money);
			break;
		case "bank":
			$sub = array_shift($param);
			$amount = array_shift($param);
			if((str_replace(" ", "", $sub) == "" or str_replace(" ", "", $amount) == "" or (!is_numeric($amount)) and $sub !== "seemoney") and $sub !== "mymoney"){
				$output .= "Usage: /$alias <deposit | withdraw | mymoney | seemoney> [amount | player]";
				break;
			}
			switch($sub){
				case "deposit":
				$this->deposit($issuer->username, $amount, $output);
				break;
				case "withdraw":
				$this->withdraw($issuer->username, $amount, $output);
				break;
				case "mymoney":
				$mymoney = $this->myBankMoney($issuer->username);
				$output .= $this->getMessage("bank-mymoney", array($mymoney, "", ""));
				break;
				case "seemoney":
				$player = $this->server->api->player->get($amount, false); // Search the player that perfectly fits
				if($player == false){
					$player = $this->server->api->player->get($amount); // Search the player that is alike
					if($player == false){
						$player = $amount; // If nobody found, search for the offline player
					}
				}
				if($player instanceof Player){
					$player = $player->username;
				}
				if($issuer->username == $player){
					$output .= "You must see your money with /bank mymoney";
					break;
				}
				if(!isset($this->bank[$player])){
					$output .= $this->getMessage("player-never-connected", array($player, "", ""));
					break;
				}
				$money = $this->myBankMoney($player);
				$output .= $this->getMessage("bank-hismoney", array($player, $money));
				break;
				default:
				$output .= "Usage: /$alias <deposit | withdraw | mymoney | seemoney> [amount | player]";
			}
		break;
		case "bankadmin":
		$sub = array_shift($param);
		$player = array_shift($param);
		$amount = array_shift($param);
		if(str_replace(" ", "", $sub) == "" or str_replace(" ", "", $player) == "" or str_replace(" ", "", $amount) == ""){
			$output .= "Usage : /bankadmin <takemoney | givemoney> [player] [amount]";
			break;
		}
		switch($sub){
			case "takemoney":
			if(!isset($this->bank[$player])){
				$output .= $this->getMessage("player-never-connected", array($player));
				break;
			} 
			if($amount < 0){
				$output .= $this->getMessage("bank-takemoney-must-bigger-than-zero", array());
				break;
			}
			if($this->bank[$player]["money"] - $amount < 0){
				$output .= $this->getMessage("bank-he-is-lack-of-credit", array($player, $amount, $this->bank[$player]["money"]));
				break;
			}
			$this->bank[$player]["money"] -= $amount;
			$output .= $this->getMessage("bank-takemoney-done", array($player, $amount, $this->bank[$player]));
			break;
			case "givemoney":
			if(!isset($this->bank[$player])){
				$output .= $this->getMessage("player-never-connected", array($player));
				break;
			}
			if($amount < 0){
				$output .= $this->getMessage("bank-givemoney-must-bigger-than-zero", array());
				break;
			}
			$this->bank[$player]["money"] += $amount;
			$output .= $this->getMessage("bank-givemoney-done", array($player, $amount, $this->bank[$player]["money"]));
			break;
			default:
			$output .= "Usage : /bankadmin <takemoney | givemoney> [player] [amount]";
		}
		break;
		case "takemoney":
			$lang = $cmd == "takemoney" ? "english" : "korean";
			$player = array_shift($param);
			$amount = array_shift($param);
			if(trim($player) == "" or trim($amount) == ""){
				$output .= "Usage: /$alias <player> [amount]";
				break;
			}
			if(!is_numeric($amount)){
				$output .= $this->getMessage("takemoney-must-be-number");
				break;
			}
			if($amount <= 0){
				$output .=  $this->getMessage("takemoney-invalid-number");
				break;
			}
			$user = $this->server->api->player->get($player, false);
			if($user == false){
				$user = $this->server->api->player->get($player);
				if($user == false){
					$user = $player;
				}
			}
			$player = $user;
			if($user instanceof Player){
				$user = $user->username;
			}
			if(!isset($this->money[$user])){
				$output .= $this->getMessage("player-never-connected", array($user, "", ""));
				break;
			}elseif($this->money[$user]["money"] - $amount < 0){
				$output .= $this->getMessage("takemoney-lack-of-money", array($user, $amount, $this->mymoney($user)));
				break;
			}
			$this->money[$user]["money"] -= $amount;
			if($player instanceof Player){
				$player->sendChat($this->getMessage("takemoney-money-taken", array($user, $amount, "", "")));
			}
			$output .= $this->getMessage("takemoney-took-money", array($amount, ""));
			break;
		case "givemoney":
			$player = array_shift($param);
			$amount = array_shift($param);
			if(trim($player) == "" or trim($amount) == ""){
				$output .= "Usage: /$alias <player> [amount]";
				break;
			}
			if(!is_numeric($amount)){
				$output .= $this->getMessage("givemoney-must-be-number");
				break;
			}
			if($amount <= 0){
				$output .= $this->getMessage("givemoney-invalid-number");
				break;
			}
			$user = $this->server->api->player->get($player, false);
			if($user == false){	
				$user = $this->server->api->player->get($player);
				if($user == false){
					$user = $player;
				}
			}
			$player = $user;
			if($user instanceof Player){
				$user = $user->username;
			}
			if(!isset($this->money[$user])){
				$output .= $this->getMessage("player-never-connected", array($user, "", ""));
				break;
			}
			$this->money[$user]["money"] += $amount;
			$output .= $this->getMessage("givemoney-gave-money", array($amount, $user, ""));
			if($player instanceof Player){
				$player->sendChat($this->getMessage("givemoney-money-given", array($amount, $issuer->username, "")));
			}
			break;
		case "seemoney":
			$player = array_shift($param);
			if(trim($player) == ""){
				$output .= "Usage: /$alias <player>";
				break;
			}
			$user = $this->server->api->player->get($player, false);
			if($user == false){
				$user = $this->server->api->player->get($player);
				if($user == false){
					$user = $player;
				}
			}
			if($user instanceof Player){
				$user = $user->username;
			}
			if(!isset($this->money[$user])){
				$output .= $this->getMessage("player-never-connected", array($user, "", ""));
				break;
			}
			$output .= $this->getMessage("seemoney-seemoney", array($user, $this->mymoney($user), ""));
			break;
		case "setmoney":
			$player = array_shift($param);
			$money = array_shift($param);
			if($player == null or $money == null or !is_numeric($money)){
				$output .= "Usage: /$alias <player> [money]";
				break;
			}
			$user = $this->server->api->player->get($player, false);
			if($user == false){
				$user = $this->server->api->player->get($player);
				if($user == false){
					$user = $player;
				}
			}
			if($user instanceof Player){
				$user = $user->username;
			}
			if(!isset($this->money[$user])){
				$output .= $this->getMessage("player-never-connected", array($user, "", ""));
				break;
			}
			$success = $this->setMoney($user, $money);
			if($success){
				$output .= $this->getMessage("setmoney-setmoney", array($user, $money, ""));
			}else{
				$output .= $this->getMessage("setmoney-failed");
			}
			break;
		case "moneyload":
			if($issuer instanceof Player){
				//$output .= "Must be run"." "."in"." "."co"."nso"."le"; This is weird!
				$output .= "Must be run in console";
				break;
			}
			$moneydat = $this->server->api->plugin->readYAML(self::$path."MoneyData.yml");
			$this->money = array();
			foreach($moneydat as $player => $data){
				$this->money[$player]["money"] = $data["money"];
				$this->money[$player]["debt"] = $data["debt"];
			}
			$bankdat = $this->server->api->plugin->readYAML(self::$path."BankData.yml");
			$this->bank = $bankdat;
			$this->checkData();
			$output .= "Money data has been loaded.";
			break;
		case "moneysave":
			if($issuer instanceof Player){
				$output .= "Must be run"." "."in"." "."co"."nso"."le";
				break;
			}
			$this->moneydata->setAll($this->money);
			$this->moneydata->save();
			$this->bankdata->setAll($this->bank);
			$this->bankdata->save();
			$output .= "Money data has been saved.";
			break;
		case "mymoney":
			$output .= $this->getMessage("mymoney-mymoney", array($this->mymoney($issuer), "", ""));
			break;
		case "mydebt":
			$output .= $this->getMessage("mydebt-mydebt", array($this->mydebt($issuer), "", ""));
			break;
		case "takedebt":
			$amount = array_shift($param);
			if(count($amount) == 0){
				$output .= "Usage  : /$alias <amount>";
			}else{
				$this->takeDebt($issuer, $amount, $output);
			}
			break;
		case "returndebt":
			$amount = array_shift($param);
			if(count($amount) == 0){
				$output .= "Usage : /$alias <amount>";
				break;
			}
			if($amount == "all"){
				$amount = $this->mydebt($issuer);
			}
			$this->returnDebt($issuer, $amount, $output);
			break;
		case "economys":
			foreach($this->economys as $k => $e){
				$output .= "[$k] $e\n";
			}
			break;
		case "mystatus":
			$all = 0;
			$debt = 0;
			foreach($this->money as $player => $data){
				if($this->server->api->ban->isBanned($player)) continue;
				if(!$this->config["add-op-at-rank"] and $this->server->api->ban->isOp($player)) continue;
				$all += $data["money"];
				$debt += $data["debt"];
			}
			$mymoneystatus = round($this->mymoney($issuer) / $all, 2);
			$mydebtstatus = round($this->mydebt($issuer) / $debt, 2);
			$output .= $this->getMessage("mystatus-show", array($mymoneystatus * 100, $mydebtstatus * 100, ""));
			break;
		case "pay":
			$player = array_shift($params);
			$amount = array_shift($params);
			if(trim($player) === "" or trim($amount) === ""){
				$output .= "Usage: /$alias <player> <amount>";
				break;
			}
			$target = $this->api->player->get($player, false);
			if(!$target instanceof Player){
				$target = $this->api->player->get($player);
				if(!$target instanceof Player){
					$target = $player;
				}
			}
			$user = $target;
			if($target instanceof Player){
				$target = $target->username;
			}
			if(isset($this->money[$target])){
				if($this->money[$issuer->username] < $amount){
					$output .= $this->getMessage("pay-lack-money", array($amount, $this->money[$issuer->username], "%3", "%4"));
					break;
				}
				$this->money[$issuer->username] -= $amount;
				$this->money[$target] += $amount;
				$output .= $this->getMessage("pay-done", array($amount, $target, "%3", "%4"));
				if($user instanceof Player){
					$user->sendChat($this->getMessage("pay-got", array($issuer->username, $amount, "%3", "%4")));
				}
			}
			break;
		}
		return $output;
	}
	
	public function getMessage($offset, $param = array("", "", "")){
		if($this->lang->exists($offset)){
			return str_replace(array("%1", "%2", "%3"), array($param[0], $param[1], $param[2]), $this->lang->get($offset));
		}else{
			return false;
		}
	}
	
	public function EconomySRegister($name){  // Can see what plugin of EconomyS enabled with command /economys
		if(is_array($this->economys)){
			if(in_array($name, $this->economys)) 
				return false;
		}
		$this->economys[] = $name;
		return true;
	}
	
	public function getMoney(){ // Returns all of the money data
		return $this->money;
	}
	
	public function delAccount($player){
		$player_instance = $player;
		if($player instanceof Player){
			$player = $player->username;
			$player_instance->close("Your account has just removed");
		}
		if(!isset($this->money[$player])){
			return false;
		}
		$this->money[$player] = null;
		$this->bank[$player] = null;
		unset($this->money[$player], $this->bank[$player]);
	}
	
	public function createAccount($player, $defaultMoney = 1000, $defaultDebt = 0, $defaultBankMoney = 0){
		if(!isset($this->money[$player])){
			$this->money[$player] = array(
				"money" => $defaultMoney,
				"debt" => $defaultDebt
			);
			$this->bank[$player] = array(
				"money" => $defaultBankMoney
			);
			return true;
		}
		return false;
	}
	
	public function mymoney($issuer){ // Returns $issuer's money
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		if(!isset($this->money[$issuer])){
			return false;
		}
		return $this->money[$issuer]["money"];
	}
	
	public function mydebt($issuer){ // Returns $issuer's money
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		if(!isset($this->money[$issuer])){
			return false;
		}
		return $this->money[$issuer]["debt"];
	}
	
	public function returnDebt($issuer, $amount, &$output = ""){ // Returns $issuer's money to the API
		if($amount <= 0){
			$output .= $this->getMessage("returndebt-must-bigger-than-zero");
			return false;
		}
		$amount = round($amount, 2);
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		$debt =& $this->money[$issuer]["debt"];
		if($debt < $amount){
			$output .= $this->getMessage("returndebt-dont-have-debt", array($amount, $this->mydebt($issuer), ""));
			return false;
		}
		elseif($this->money[$issuer]["money"] < $amount){
			$output .= $this->getMessage("returndebt-dont-have-money", array($amount, $this->mymoney($issuer), ""));
			return false;
		}else{
			if($this->server->dhandle("economys.debt.return", array("player" => $issuer, "amount" => $amount)) !== false){
				$debt -= $amount;
				$this->money[$issuer]["money"] -= $amount;
				$output .= $this->getMessage("returndebt-returndebt", array($amount, $debt, ""));
				return true;
			}else{
				$output .= $this->getMessage("returndebt-failed");
			}
		}
	}
	
	public function setMoney($player, $amount){ // Sets $player's money to $<$amount>
		$amount = round($amount, 2);
		if($amount < 0){
			return false;
		}
		if($player instanceof Player){
			$player = $player->username;
		}
		if(isset($this->money[$player])){
			if($this->server->dhandle("economys.money.set", array("player" => $player, "amount" => $amount)) !== false){
				$this->money[$player]["money"] = $amount;
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	
	public function takeDebt($issuer, $amount, &$output = ""){ // $issuer take $amount of debt
		if($amount <= 0){
			$output .= $this->getMessage("takedebt-must-bigger-than-zero");
			return false;
		}
		$amount = round($amount, 2);
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		$debt =& $this->money[$issuer]["debt"];
		if($debt + $amount > $this->config["debt-limit"]){
			$output .= $this->getMessage("takedebt-over-range", array($amount, $this->config["debt-limit"], ""));
			return false;
		}elseif($this->config["once-debt-limit"] < $amount){
			$output .= $this->getMessage("takedebt-over-range-once", array($amount, $this->config["once-debt-limit"], ""));
			return false;
		}else{
			if($this->server->dhandle("economys.debt.take", array("player" => $issuer, "amount" => $amount)) !== false){
				$debt += $amount;
				$this->money[$issuer]["money"] += $amount;
				$output .= $this->getMessage("takedebt-takedebt", array($amount, $debt, ""));
				if(!isset($this->schedule[$issuer])){
					$this->server->api->schedule(20 * 60 * ((float) $this->config["time-for-increase-debt"]), array($this, "increaseDebt"), $issuer, true);
					$this->schedule[$issuer] = array("tick" => 20 * 60 * $this->config["time-for-increase-debt"], "player" => $issuer);
				}
				$debt = round($debt, 2);
				return true;
			}else{
				$output .= $this->getMessage("takedebt-failed");
				return false;
			}
		}
	}
	
	public function useMoney($issuer, $amount){
		if($amount <= 0){
			return false;
		}
		$amount = round($amount, 2);
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		$money =& $this->money[$issuer]["money"];
		if($money < $amount){
			return false;
		}else{
			if($this->server->dhandle("economys.usemoney", array("player" => $issuer, "money" => $amount)) !== false){
				$money -= $amount;
				$money = round($money, 2);
				return true;
			}else{
				return false;
			}
		}
	}
	
	public function takeMoney($issuer, $amount){
		if($amount <= 0){
			return false;
		}
		$amount = round($amount, 2);
		if($issuer instanceof Player){
			$issuer = $issuer->username;
		}
		if($this->server->dhandle("economys.takemoney", array("player" => $issuer, "money" => $amount)) !== false){
			//$money =& $this->money[$issuer]["money"];
			$this->money[$issuer]["money"] += $amount;
		}
		$this->money[$issuer]["money"] = round($this->money[$issuer]["money"], 2);
		return true;
	}
	
	public function deposit($username, $amount, &$output = ""){
		if($amount <= 0){
			$output .= $this->getMessage("bank-deposit-must-bigger-than-zero");
			return false;
		}
		$amount = round($amount, 2);
		if(!isset($this->bank[$username])){
			return false;
		}
		if($this->money[$username]["money"] - $amount < 0){
			$output .= $this->getMessage("bank-deposit-dont-have-money", array($amount, $this->mymoney($username), ""));
			return false;
		}
		//money =& $this->bank[$username]["money"];
		if($this->server->dhandle("economys.bank.deposit", array("player" => $username, "money" => $amount)) !== false){
			if(!isset($this->bankSchedule[$username])){
				$this->server->api->schedule(1200 * $this->config["time-for-increase-money"], array($this, "increaseMoney"), $username, true);
				$this->bankSchedule[$username] = array(
					//"tick" => 1200 * $this->config["time-for-increase-money"],
					"player" => $username
				);
			}
			$this->bank[$username]["money"] += $amount;
			$this->money[$username]["money"] -= $amount;
			$output .= $this->getMessage("bank-deposit-success", array($amount, "", ""));
			return true;
		}else{
			$output .= $this->getMessage("bank-deposit-failed");
			return false;
		}
	}
	
	public function withdraw($username, $amount, &$output = ""){
		if($amount <= 0){
			$output .= $this->getMessage("bank-withdraw-must-bigger-than-zero");
			return false;
		}
		$amount = round($amount, 2);
		if(!isset($this->bank[$username])){
			return false;
		}
		$money =& $this->bank[$username]["money"];
		if($money - $amount < 0){
			$output .= $this->getMessage("bank-withdraw-lack-of-credit", array($amount, $this->myBankMoney($username), ""));
			return false;
		}
		if($this->server->dhandle("economys.bank.withdraw", array("player" => $username, "money" => $amount)) !== false){
			$money -= $amount;
			$this->money[$username]["money"] += $amount;
			$output .= $this->getMessage("bank-withdraw-success", array($amount, $this->myBankMoney($username), ""));
			return true;
		}else{
			$output .= $this->getMessage("bank-withdraw-failed");
			return false;
		}
	}
	
	public function myBankMoney($username){ // Returns $username's bank credit
		if(!isset($this->bank[$username])){
			return false;
		}else{
			return $this->bank[$username]["money"];
		}
	}
}	 
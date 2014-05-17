<?php

namespace EconomyAPI;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;

use EconomyAPI\event\money\AddMoneyEvent;
use EconomyAPI\event\money\ReduceMoneyEvent;
use EconomyAPI\event\money\SetMoneyEvent;
use EconomyAPI\event\account\CreateAccountEvent;
use EconomyAPI\event\debt\AddDebtEvent;
use EconomyAPI\event\debt\ReduceDebtEvent;

class EconomyAPI extends PluginBase implements Listener{
	private static $obj = null;
	private $path;
	private $money, $bank;
	private $config, $lang;
	private $schedule, $task;
	private $list, $playerLang, $langRes;
	
	public $lastTask;
	
	const RET_ERROR_1 = -4;
	const RET_ERROR_2 = -3;
	const RET_CANCELLED = -2;
	const RET_NOT_FOUND = -1;
	const RET_INVALID = 0;
	const RET_SUCCESS = 1;
	
	private $langList = array(
		"def" => "Default",
		"en" => "English",
		"ko" => "한국어",
		"user-define" => "User Define"
	);
	
	public static function getInstance(){
		return self::$obj;
	}
	
	public function onLoad(){
		self::$obj = $this;
		$this->path = $this->getDataFolder();
		$this->schedule = array();
		$this->list = array();
		$this->money = array();
		$this->bank = array();
		$this->task = array();
		$this->lastTask = array();
		$this->playerLang = array();
		$this->langRes = array();
	}
	
	public function onEnable(){
		@mkdir($this->path);
		
		$this->createConfig();
		$this->scanResources();
		
		file_put_contents($this->path."ReadMe.txt", $this->getResource("ReadMe.txt"));
		if(!is_file($this->path."ScheduleData.dat")){
			file_put_contents($this->path."ScheduleData.dat", serialize(array(
				"debt" => array(),
				"bank" => array()
			)));
		}
		if(!is_file($this->path."PlayerLang.dat")){
			file_put_contents($this->path."PlayerLang.dat", serialize(array()));
		}
		
		$this->schedule = unserialize(file_get_contents($this->path."ScheduleData.dat"));
		$playerLang = unserialize(file_get_contents($this->path."PlayerLang.dat"));
		
		if(isset($playerLang["CONSOLE"])){
			$this->config->set("default-lang", $playerLang["CONSOLE"]);
			$this->config->save();
		}
		
		$this->initializePlayerLang($playerLang);
		$this->getLangFile();
		
		$cmds = array(
			new \EconomyAPI\commands\SetMoneyCommand($this),
			new \EconomyAPI\commands\SeeMoneyCommand($this),
			new \EconomyAPI\commands\MyMoneyCommand($this),
			new \EconomyAPI\commands\PayCommand($this),
			new \EconomyAPI\commands\MyMoneyCommand($this),
			new \EconomyAPI\commands\GiveMoneyCommand($this),
			new \EconomyAPI\commands\TakeDebtCommand($this),
			new \EconomyAPI\commands\EconomySCommand($this),
			new \EconomyAPI\commands\TopMoneyCommand($this),
			new \EconomyAPI\commands\SetLangCommand($this),
			new \EconomyAPI\commands\TakeMoneyCommand($this)
		);
		$commandMap = $this->getServer()->getCommandMap();
		foreach($cmds as $cmd){
			$commandMap->register("economyapi", $cmd);
		}
		
		// getServer().getPluginManager().registerEvents(this, this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$moneyConfig = new Config($this->path."MoneyData.yml", Config::YAML);
		$bankConfig = new Config($this->path."BankData.yml", Config::YAML);
		$this->money = $moneyConfig->getAll();
		$this->bank = $bankConfig->getAll();
		$this->registerList("EconomyAPI");
	}
	
	private function initializePlayerLang($langData){
		foreach($langData as $player => $lang){
			$this->playerLang[$player] = $this->langRes[$lang];
		}
	}
	
	private function getSavingPlayerLangData(){
		$ret = array();
		foreach($this->playerLang as $player => $lang){
			$ret[$player] = $lang["language"];
		}
		return $ret;
	}
	
	private function createConfig(){
		$this->config = new Config($this->path."economy.properties", Config::PROPERTIES, unserialize($this->getResource("config.txt")));
	}
	
	private function scanResources(){
		foreach($this->getResources() as $resource){
			$s = explode("\\", $resource);
			$res = $s[count($s) - 1];
			if(substr($res, 0, 5) === "lang_"){
				$this->langRes[substr($res, 5, -5)] = get_object_vars(json_decode($this->getResource($res)));
			}
		}
	}
	
	public function registerList($name){
		if(trim($name) === ""){
			return false;
		}
		if(in_array($name, $this->list)){
			return false;
		}else{
			$this->list[] = $name;
			return true;
		}
	}
	
	public function getList(){
		return $this->list;
	}
	
	public function getConfigurationValue($key, $default = false){
		if($this->config->exists($key)){
			return $this->config->get($key);
		}
		return $default;
	}
	
	private function getLangFile(){
		$lang = $this->config->get("default-lang");
		if(($resource = $this->getResource("lang_".$lang.".json")) !== false){
			$vars = get_object_vars(json_decode($resource));
			$this->playerLang["CONSOLE"] = $vars;
			$this->playerLang["RCON"] = $vars;
			\pocketmine\console(TextFormat::GREEN."[EconomyAPI] ".$this->getMessage("language-set", "CONSOLE", array($this->langList[$lang], "%2", "%3", "%4")));
		}elseif($lang === "user-define"){
			$vars = (new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, get_object_vars(json_decode($this->getResource("lang_def.json")))))->getAll();
			$this->playerLang["CONSOLE"] = $vars;
			$this->playerLang["RCON"] = $vars;
			\pocketmine\console(TextFormat::GREEN."[EconomyAPI] ".$this->getMessage("language-set", "CONSOLE", array("User Define", "%2", "%3", "%4")));
		}else{
			$vars = get_object_vars(json_decode($this->getResource("lang_def.json")));
			$this->playerLang["CONSOLE"] = $vars;
			$this->playerLang["RCON"] = $vars;
			\pocketmine\console(TextFormat::GREEN."[EconomyAPI] ".$this->getMessage("language-set", "CONSOLE", array($this->langList[$lang], "%2", "%3", "%4")));
		}
	}
	
	/*
	@param string $lang
	
	@return boolean
	*/
	public function setLang($lang, $target = "CONSOLE"){
		if(($resource = $this->getResource("lang_".$lang.".json")) !== false){
			$this->playerLang[$target] = get_object_vars(json_decode($resource));
			\pocketmine\console(TextFormat::GREEN."[EconomyAPI] ".$this->getMessage("language-set", $target, array($this->langList[$lang], "%2", "%3", "%4")));
			return true;
		}else{
			foreach($this->langList as $key => $l){
				if(strtolower($lang) === strtolower($l)){
					if(($resource = $this->langList($key)) !== false){
						/*$this->playerLang[$target] = get_object_vars(json_decode($resource));*/
						$this->playerLang[$target] = $resource;
						\pocketmine\console(TextFormat::GREEN."[EconomyAPI] ".$this->getMessage("language-set", $target, array($l, "%2", "%3", "%4")));
						return $l;
					}else{
						return false;
					}
				}
			}
			return false;
		}
	}
	
	/*
	@return array
	*/
	public function getLangList(){
		return $this->langList;
	}
	
	/*
	@return array
	*/
	public function getLangResource(){
		return $this->langRes;
	}
	
	/*
	@param string|Player $player
	
	@return string|boolean
	*/
	public function getPlayerLang($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(isset($this->playerLang[$player])){
			return $this->playerLang[$player];
		}else{
			return false;
		}
	}
	
	/*
	@param Player|string $player
	
	@return int
	*/
	public function addDebt($player, $amount, $force = false, $issuer = "external"){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$amount = round($amount, 2);
		if(isset($this->money[$player])){
			$debt = $this->money[$player]["debt"];
			if($amount <= 0){
				return self::RET_INVALID;
			}
			if(($debt + $amount > $this->config->get("debt-limit")) and $force === false){
				return self::RET_ERROR_1;
			}
			if((($amount > $this->config->get("once-debt-limit")) and $force === false)){
				return self::RET_ERROR_2;
			}
			$ev = new AddDebtEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money[$player]["debt"] += $amount;
			
			$this->schedule["debt"][$player] = time();
			$this->task[$player]["debt"] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new DebtScheduler($this, $player), $this->config->get("time-for-increase-debt"))->getTaskId();
			$this->lastTask["debt"][$player] = time();
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@param Player|string $player
	
	@return int
	*/
	public function reduceDebt($player, $amount, $force = false, $issuer = "external"){
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		$amount = round($amount, 2);
		if(isset($this->money[$player])){
			$debt = $this->money[$player]["debt"];
			if($amount <= 0 or $debt < $amount){
				return self::RET_INVALID;
			}
			$ev = new ReduceDebtEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money[$player]["debt"] -= $amount;
			if($this->money[$player]["debt"] <= 0){
				$this->getServer()->getScheduler()->cancelTask($this->task[$player]["debt"]);
				$this->lastTask["debt"][$player] = null;
				$this->schedule["debt"][$player] = null;
				unset($this->task[$player]["debt"], $this->schedule["debt"][$player], $this->lastTask["debt"][$player]);
			}
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@param Player|string $player
	
	@return int
	*/
	public function addBankMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0){
			return self::RET_INVALID;
		}
		if($player instanceof Player){
			$player = $player->getName();
		}
		$amount = round($amount, 2);
		if(isset($this->bank[$player])){
			$ev = new \EconomyAPI\event\bank\AddMoneyEvent($this, $player, $amount, $issuer); // I have to declare package because there's same named class at \EconomyAPI\event\money\AddMoneyEvent
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->bank[$player]["money"] += $amount;
			
			$this->schedule["bank"][$player] = time();
			$this->task[$player]["bank"] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new BankScheduler($this, $player), $this->schedule["bank"][$player] * 20)->getTaskId();
			$this->lastTask["bank"][$player] = time();
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@param Player|string $player
	
	@return int
	*/
	public function reduceBankMoney($player, $amount, $force = false, $issuer = "external"){
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		$amount = round($amount, 2);
		if(isset($this->bank[$player])){
			if($amount <= 0 or $amount > $this->bank[$player]["money"]){
				return self::RET_INVALID;
			}
			$ev = new \EconomyAPI\event\bank\ReduceMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->bank[$player]["money"] -= $amount;
			if($this->bank[$player]["money"] <= 0){
				$this->getServer()->getScheduler()->cancelTask($this->task[$player]["bank"]);
				$this->schedule["bank"][$player] = null;
				$this->lastTask["bank"][$player] = null;
				unset($this->schedule["bank"][$player], $this->lastTask["bank"][$player], $this->task[$player]["bank"]);
			}
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@return Map<string, Map<string, int>>
	*/
	public function getAllMoney(){
		return $this->money;
	}
	
	/*
	@return Map<string, Map<string, int>>
	*/
	public function getAllBankMoney(){
		return $this->bank;
	}
	
	/*
	@return Map<string, int>
	*/
	public function sortMoney(){
		$cnt = 0;
		$data = array();
		$banList = $this->getServer()->getNameBans();
		foreach($this->money as $p => $m){
			if($banList->isBanned($p)) continue;
			$data[$m["money"]][] = $p;
			++$cnt;
		}
		$max = ceil($cnt / 5);
		krsort($data);
		$n = 1;
		$ret = array();
		foreach($data as $money => $players){
			$current = (int)ceil($n / 5);
			foreach($players as $player){
				$ret[$player] = $money;
				++$n;
			}
		}
		return $ret;
	}
	
	/*
	@param string $key
	@param string|Player $player
	@param array $value
	
	@return string
	*/
	public function getMessage($key, $player = "CONSOLE", array $value = array("%1", "%2", "%3", "%4")){
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(isset($this->playerLang[$player][$key])){
			return str_replace(array("%1", "%2", "%3", "%4"), array($value[0], $value[1], $value[2], $value[3]), $this->playerLang[$player][$key]);
		}else{
			return "Couldn't find message resource";
		}
	}
	
	/*
	@param Player|string $player
	
	@return boolean
	*/
	public function accountExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		return isset($this->money[$player]);
	}
	
	/*
	@param Player|string $player
	@param int $default_money
	@param int $default_debt
	@param int $default_bank_money
	
	@return boolean
	*/
	public function createAccount($player, $default_money = false, $default_debt = false, $default_bank_money = false){
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(isset($this->money[$player])){
			$this->money[$player] = array(
				"money" => ($default_money === false ? $this->config->get("default-money") : $default_money),
				"debt" => ($default_debt === false ? $this->config->get("default-debt") : $default_debt)
			);
			$this->bank[$player] = array(
				"money" => ($default_bank_money === false ? $this->config->get("default-bank-money") : $default_bank_money)
			);
			return true;
		}
		return false;
	}
	
	/*
	@param Player|string $player
	
	@return boolean
	*/
	public function removeAccount($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(isset($this->money[$player])){
			$this->money[$player] = null;
			$this->bank[$player] = null;
			unset($this->money[$player], $this->bank[$player]);
			return true;
		}
		return false;
	}
	
	/*
	@param Player|string $player
	
	@return boolean
	*/
	public function bankAccountExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		return isset($this->bank[$player]);
	}
	
	/*
	@param Player|string $player
	
	@return boolean|int
	*/
	public function myMoney($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(!isset($this->money[$player]["money"])){
			return false;
		}
		return $this->money[$player]["money"];
	}
	
	/*
	@param Player|string $player
	
	@return boolean|int
	*/
	public function myDebt($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(!isset($this->money[$player]["debt"])){
			return false;
		}
		return $this->money[$player]["debt"];
	}
	
	/*
	@param Player|string $player
	
	@return boolean|int
	*/
	public function myBankMoney($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		if(!isset($this->bank[$player]["money"])){
			return false;
		}
		return $this->bank[$player]["money"];
	}
	
	/*
	@param Player|string $player
	@param int $amount
	
	@return int
	*/
	public function addMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0 or !is_numeric($amount)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		$amount = round($amount, 2);
		if(isset($this->money[$player])){
			$event = new AddMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($event);
			if($force === false and $event->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money[$player]["money"] += $amount;
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@param Player|string $player
	@param int $amount
	
	@return int
	*/
	public function reduceMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0 or !is_numeric($amount)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		$amount = round($amount, 2);
		if(isset($this->money[$player]["money"])){
			if($this->money[$player]["money"] - $amount < 0){
				return self::RET_INVALID;
			}
			$event = new ReduceMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($event);
			if($force === false and $event->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money[$player]["money"] -= $amount;
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	/*
	@param Player|string $player
	@param int $money
	
	@return int
	*/
	public function setMoney($player, $money, $force = false, $issuer = "external"){
		if($money < 0 or !is_numeric($money)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		
		$money = round($money, 2);
		if(isset($this->money[$player])){
			$ev = new SetMoneyEvent($this, $player, $money, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money[$player]["money"] = $money;
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	public function onDisable(){
		$moneyConfig = new Config($this->path."MoneyData.yml", Config::YAML);
		$bankConfig = new Config($this->path."BankData.yml", Config::YAML);
		$moneyConfig->setAll($this->money);
		$moneyConfig->save();
		$bankConfig->setAll($this->bank);
		$bankConfig->save();
		file_put_contents($this->path."ScheduleData.dat", serialize($this->schedule));
		file_put_contents($this->path."PlayerLang.dat", serialize($this->getSavingPlayerLangData()));
	}
	
	public function onQuitEvent(PlayerQuitEvent $event){
		$username = $event->getPlayer()->getName();
		if(isset($this->task[$username])){
			$now = time();
			if(isset($this->task[$username]["debt"])){
				$this->schedule["debt"][$username] -= ($now - $this->lastTask["debt"][$username]);
				$this->getServer()->getScheduler()->cancelTask($this->task[$username]["debt"]);
				unset($this->task[$username]["debt"]);
			}
			if(isset($this->task[$username]["bank"])){
				$this->schedule["bank"][$username] -= ($now - $this->lastTask["bank"][$username]);
				$this->getServer()->getScheduler()->cancelTask($this->task[$username]["bank"]);
				unset($this->task[$username]["bank"]);
			}
		}
	}
	
	public function onJoinEvent(PlayerJoinEvent $event){
		$username = $event->getPlayer()->getName();
		if(!isset($this->money[$username])){
			$this->money[$username] = array(
				"money" => $this->config->get("default-money"),
				"debt" => $this->config->get("default-debt")
			);
		}
		if(!isset($this->bank[$username])){
			$this->bank[$username] = array(
				"money" => $this->config->get("default-bank-money")
			);
		}
		if(!isset($this->playerLang[$username])){
			$this->playerLang[$username] = $this->setLang($this->config->get("default-lang"), $username);
		}
		if(isset($this->schedule["debt"][$username])){
			$this->task[$username]["debt"] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new DebtScheduler($this, $username), $this->schedule["debt"][$username] * 20)->getTaskId();
		}
		if(isset($this->schedule["bank"][$username])){
			$this->task[$username]["bank"] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new BankScheduler($this, $username), $this->schedule["bank"][$username] * 20)->getTaskId();
		}
	}
	
	/*
	@return string
	*/
	public function __toString(){
		return "EconomyAPI (accounts:".count($this->money).", bank: ".count($this->money).")";
	}
}
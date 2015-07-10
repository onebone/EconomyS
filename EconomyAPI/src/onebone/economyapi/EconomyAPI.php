<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2015  onebone <jyc00410@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyapi;

use onebone\economyapi\event\money\MoneyChangedEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use onebone\economyapi\event\money\AddMoneyEvent;
use onebone\economyapi\event\money\ReduceMoneyEvent;
use onebone\economyapi\event\money\SetMoneyEvent;
use onebone\economyapi\event\account\CreateAccountEvent;
use onebone\economyapi\database\DataConverter;
use onebone\economyapi\task\SaveTask;

class EconomyAPI extends PluginBase implements Listener{
	/**
	 * @var int
	 */
	const API_VERSION = 1;
	
	/**
	 * @var EconomyAPI
	 */
	private static $instance = null;

	/**
	 * @var array
	 */
	private $money = [];

	/**
	 * @var Config
	 */
	private $config = null;

	/**
	 * @var Config
	 */
	private $command = null;

	/**
	 * @var array
	 */
	private $langRes = [];

	/**
	 * @var array
	 */
	private $playerLang = []; // language system related

	/**
	 * @var string
	 */
	private $monetaryUnit = "$";
	
	/**
	 * @var int RET_ERROR_1 Unknown error 1
	*/
	const RET_ERROR_1 = -4;
	
	/**
	 * @var int RET_ERROR_2 Unknown error 2
	*/
	const RET_ERROR_2 = -3;
	
	/**
	@var int RET_CANCELLED Task cancelled by event
	*/
	const RET_CANCELLED = -2;
	
	/**
	 * @var int RET_NOT_FOUND Unable to process task due to not found data
	*/
	const RET_NOT_FOUND = -1;
	
	/**
	 * @var int RET_INVALID Invalid amount of data
	*/
	const RET_INVALID = 0;
	
	/**
	 * @var int RET_SUCCESS The task was successful
	*/
	const RET_SUCCESS = 1;

	/**
	 * @var int CURRENT_DATABASE_VERSION The version of current database
	 */
	const CURRENT_DATABASE_VERSION = 0x02;
	
	/**
	 * @var array
	 */
	private $langList = [
		"def" => "Default",
		"en" => "English",
		"ko" => "한국어",
		"it" => "Italiano",
		"ch" => "中文",
		"id" => "Bahasa Indonesia",
		"ru" => "русский",
		"ja" => "日本語",
		"user-define" => "User Defined"
	];

	/**
	 * @return EconomyAPI
	 */
	public static function getInstance(){
		return self::$instance;
	}
	
	public function onLoad(){
		self::$instance = $this;
	}
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		
		$this->createConfig();
		$this->scanResources();
		
		file_put_contents($this->getDataFolder() . "ReadMe.txt", $this->readResource("ReadMe.txt"));
		if(!is_file($this->getDataFolder() . "PlayerLang.dat")){
			file_put_contents($this->getDataFolder() . "PlayerLang.dat", serialize([]));
		}
		
		$this->playerLang = unserialize(file_get_contents($this->getDataFolder() . "PlayerLang.dat"));

		if(!isset($this->playerLang["console"])){
			$this->getLangFile();
		}
		$commands = [
			"setmoney" => "onebone\\economyapi\\commands\\SetMoneyCommand",
			"seemoney" => "onebone\\economyapi\\commands\\SeeMoneyCommand",
			"mymoney" => "onebone\\economyapi\\commands\\MyMoneyCommand",
			"pay" => "onebone\\economyapi\\commands\\PayCommand",
			"givemoney" => "onebone\\economyapi\\commands\\GiveMoneyCommand",
			"topmoney" => "onebone\\economyapi\\commands\\TopMoneyCommand",
			"setlang" => "onebone\\economyapi\\commands\\SetLangCommand",
			"takemoney" => "onebone\\economyapi\\commands\\TakeMoneyCommand",
			"mystatus" => "onebone\\economyapi\\commands\\MyStatusCommand"
		];
		$commandMap = $this->getServer()->getCommandMap();
		foreach($commands as $key => $command){
			foreach($this->command->get($key) as $cmd){
				$commandMap->register("economyapi", new $command($this, $cmd));
			}
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->convertData();
		$moneyConfig = new Config($this->getDataFolder() . "Money.yml", Config::YAML, [
			"version" => 2,
			"money" => [],
		]);
		
		if($moneyConfig->get("version")< self::CURRENT_DATABASE_VERSION){
			$converter = new DataConverter($this->getDataFolder() . "Money.yml");
			$result = $converter->convertData(self::CURRENT_DATABASE_VERSION);
			if($result !== false){
				$this->getLogger()->info("Converted data into new database. Database version : ".self::CURRENT_DATABASE_VERSION);
			}
			$moneyConfig = new Config($this->getDataFolder() . "Money.yml", Config::YAML);
		}
		$this->money = $moneyConfig->getAll();
		
		$this->monetaryUnit = $this->config->get("monetary-unit");
		
		$time = $this->config->get("auto-save-interval");
		if(is_numeric($time)){
			$interval = $time * 1200;
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new SaveTask($this), $interval, $interval);
			$this->getLogger()->notice("Auto save has been set to interval : ".$time." min(s)");
		}
		
		if($this->config->get("check-update")){
			try{
				$this->getLogger()->info("Checking for updates... It may be take some while.");

				$desc = yaml_parse(Utils::getURL("https://raw.githubusercontent.com/onebone/EconomyS/master/EconomyAPI/plugin.yml"));
				
				$description = $this->getDescription();
				if(version_compare($description->getVersion(), $desc["version"]) < 0){
					$this->getLogger()->warning("New version of EconomyAPI (v".$desc["version"].") has been found. Current version : v".$description->getVersion().". Please update the plugin.");
				}else{
					$this->getLogger()->notice("EconomyAPI is currently up-to-date.");
				}
				
				if($desc["author"] !== $description->getAuthors()[0]){
					$this->getLogger()->warning("You are using the modified version of the plugin. This version could not be supported.");
				}
			}catch(\Exception $e){
				$this->getLogger()->warning("An exception during check-update has been detected.");
			}
		}
	}
	
	private function convertData(){
		$cnt = 0;
		if(is_file($this->getDataFolder() . "MoneyData.yml")){
			$data = (new Config($this->getDataFolder() . "MoneyData.yml", Config::YAML))->getAll();
			$saveData = [];
			foreach($data as $player => $money){
				$saveData["money"][$player] = round($money["money"], 2);
				++$cnt;
			}
			@unlink($this->getDataFolder() . "MoneyData.yml");
			$moneyConfig = new Config($this->getDataFolder() . "Money.yml", Config::YAML);
			$moneyConfig->setAll($saveData);
			$moneyConfig->save();
		}
		if($cnt > 0){
			$this->getLogger()->info(TextFormat::AQUA."Converted $cnt data(m) into new format");
		}
	}
	
	private function createConfig(){
		$this->config = new Config($this->getDataFolder() . "economy.properties", Config::PROPERTIES, yaml_parse($this->readResource("config.yml")));
		$this->command = new Config($this->getDataFolder() . "command.yml", Config::YAML, yaml_parse($this->readResource("command.yml")));
	}
	
	private function scanResources(){
		foreach($this->getResources() as $resource){
			$s = explode(\DIRECTORY_SEPARATOR, $resource);
			$res = $s[count($s) - 1];
			if(substr($res, 0, 5) === "lang_"){
				$this->langRes[substr($res, 5, -5)] = get_object_vars(json_decode($this->readResource($res)));
			}
		}
		$this->langRes["user-define"] = (new Config($this->getDataFolder() . "language.properties", Config::PROPERTIES, $this->langRes["def"]))->getAll();
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function getConfigurationValue($key, $default = false){
		if($this->config->exists($key)){
			return $this->config->get($key);
		}
		return $default;
	}

	/**
	 * @param string $res
	 *
	 * @return bool|string
	 */
	private function readResource($res){
		$resource = $this->getResource($res);
		if($resource !== null){
			return stream_get_contents($resource);
		}
		return false;
	}
	
	private function getLangFile(){
		$lang = $this->config->get("default-lang");
		if(isset($this->langRes[$lang])){
			$this->playerLang["console"] = $lang;
			$this->playerLang["rcon"] = $lang;
			$this->getLogger()->info(TextFormat::GREEN.$this->getMessage("language-set", "console", [$this->langList[$lang], "%2", "%3", "%4"]));
		}else{
			$this->playerLang["console"] = "def";
			$this->playerLang["rcon"] = "def";
			$this->getLogger()->info(TextFormat::GREEN.$this->getMessage("language-set", "console", [$this->langList[$lang], "%2", "%3", "%4"]));
		}
	}

	/**
	 * @param string $lang
	 * @param string $target
	 *
	 * @return bool
	 */
	public function setLang($lang, $target = "console"){
		if(isset($this->langRes[$lang])){
			$this->playerLang[strtolower($target)] = $lang;
			return $lang;
		}else{
			$lower = strtolower($lang);
			foreach($this->langList as $key => $l){
				if($lower === strtolower($l)){
					$this->playerLang[strtolower($target)] = $key;
					return $l;
				}
			}
		}
		return false;
	}
	
	/**
	 * @return array
	*/
	public function getLangList(){
		return $this->langList;
	}
	
	/**
	 * @return array
	*/
	public function getLangResource(){
		return $this->langRes;
	}
	
	/**
	 * @param string|Player $player
	 *
	 * @return string|boolean
	*/
	public function getPlayerLang($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(isset($this->playerLang[$player])){
			return $this->playerLang[$player];
		}else{
			return false;
		}
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	*/
	public function addDebt($player, $amount, $force = false, $issuer = "external"){
		$this->getLogger()->warning("Debt system is now deprecated");
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	*/
	public function reduceDebt($player, $amount, $force = false, $issuer = "external"){
		$this->getLogger()->warning("Debt system is now deprecated");
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	*/
	public function addBankMoney($player, $amount, $force = false, $issuer = "external"){
		$this->getLogger()->warning("Bank system is now deprecated.");
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	*/
	public function reduceBankMoney($player, $amount, $force = false, $issuer = "external"){
		$this->getLogger()->warning("Bank system is now deprecated");
	}
	
	/**
	 * @return array
	*/
	public function getAllMoney(){
		return $this->money;
	}
	
	/**
	 * @deprecated
	 *
	 * @return array
	*/
	public function getAllBankMoney(){
		$this->getLogger()->warning("Bank system is now deprecated");
	}
	
	/**
	  * @return string
	  */
	 public function getMonetaryUnit(){
		return $this->monetaryUnit;
	 }
	
	/**
	 * @param string $key
	 * @param Player|string $player
	 * @param array $value
	 *
	 * @return string
	*/
	public function getMessage($key, $player = "console", array $value = ["%1", "%2", "%3", "%4"]){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(isset($this->playerLang[$player]) and isset($this->langRes[$this->playerLang[$player]][$key])){
			return str_replace(["%MONETARY_UNIT%", "%1", "%2", "%3", "%4"], [$this->monetaryUnit, $value[0], $value[1], $value[2], $value[3]], $this->langRes[$this->playerLang[$player]][$key]);
		}elseif(isset($this->langRes["def"][$key])){
			return str_replace(["%MONETARY_UNIT%", "%1", "%2", "%3", "%4"], [$this->monetaryUnit, $value[0], $value[1], $value[2], $value[3]], $this->langRes["def"][$key]);
		}else{
			return "Couldn't find message resource";
		}
	}
	
	/**
	 * @param Player|string $player
	 *
	 * @return boolean
	*/
	public function accountExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		return isset($this->money["money"][$player]) === true;
	}

	/**
	 * @param Player|string $player
	 * @param bool|float $default_money
	 * @param bool $force
	 *
	 * @return boolean
	 */
	public function createAccount($player, $default_money = false, $force = false){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->money["money"][$player])){
			$this->getServer()->getPluginManager()->callEvent(($ev = new CreateAccountEvent($this, $player, $default_money, "EconomyAPI")));
			if(!$ev->isCancelled() and $force === false){
				$this->money["money"][$player] = ($default_money === false ? $this->config->get("default-money") : $default_money);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * @param Player|string $player
	 *
	 * @return boolean
	*/
	public function removeAccount($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(isset($this->money["money"][$player])){
			$this->money["money"][$player] = null;
			unset($this->money["money"][$player]);
			
			$p = $this->getServer()->getPlayerExact($player);
			if($p instanceof Player){
				$p->kick("Your account have been removed.");
			}
			return true;
		}
		return false;
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 *
	 * @return boolean
	*/
	public function bankAccountExists($player){
		$this->getLogger()->warning("Bank system is now deprecated");
	}
	
	/**
	 * @param Player|string $player
	 *
	 * @return boolean|float
	*/
	public function myMoney($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->money["money"][$player])){
			return false;
		}
		return $this->money["money"][$player];
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 *
	 * @return boolean|float
	*/
	public function myDebt($player){ // To identify the result, use '===' operator
		$this->getLogger()->warning("Debt system is now deprecated");
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 *
	 * @return boolean|float
	*/
	public function myBankMoney($player){
		$this->getLogger()->warning("Bank system is now deprecated");
	}

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function addMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0 or !is_numeric($amount)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$amount = round($amount, 2);
		if(isset($this->money["money"][$player])){
			$amount = min($this->config->get("max-money"), $amount);
			$event = new AddMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($event);
			if($force === false and $event->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money["money"][$player] += $amount;
			$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $this->money["money"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}

	/**
	 * @param Player|string $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function reduceMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0 or !is_numeric($amount)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$amount = round($amount, 2);
		if(isset($this->money["money"][$player])){
			if($this->money["money"][$player] - $amount < 0){
				return self::RET_INVALID;
			}
			$event = new ReduceMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($event);
			if($force === false and $event->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money["money"][$player] -= $amount;
			$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $this->money["money"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}

	/**
	 * @param Player|string $player
	 * @param float $money
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function setMoney($player, $money, $force = false, $issuer = "external"){
		if($money < 0 or !is_numeric($money)){
			return self::RET_INVALID;
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$money = round($money, 2);
		if(isset($this->money["money"][$player])){
			$money = min($this->config->get("max-money"), $money);
			$ev = new SetMoneyEvent($this, $player, $money, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money["money"][$player] = $money;
			$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $this->money["money"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
	}
	
	public function onDisable(){
		$this->save();
	}
	
	public function save(){
		$moneyConfig = new Config($this->getDataFolder() . "Money.yml", Config::YAML);
		$moneyConfig->setAll($this->money);
		$moneyConfig->save();
		file_put_contents($this->getDataFolder() . "PlayerLang.dat", serialize($this->playerLang));
	}

	/**
	 * @param PlayerLoginEvent $event
	 */
	public function onLoginEvent(PlayerLoginEvent $event){
		$username = strtolower($event->getPlayer()->getName());
		if(!isset($this->money["money"][$username])){
			$this->getServer()->getPluginManager()->callEvent(($ev = new CreateAccountEvent($this, $username, $this->config->get("default-money"), $this->config->get("default-debt"), null, "EconomyAPI")));
			$this->money["money"][$username] = round($ev->getDefaultMoney(), 2);
		}
		if(!isset($this->playerLang[$username])){
			$this->setLang($this->config->get("default-lang"), $username);
		}
	}
	
	/**
	 * @param PlayerCommandPreprocessEvent $event
	 */
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		$command = strtolower(substr($event->getMessage(), 0, 9));
		if($command === "/save-all"){
			$this->onCommandProcess($event->getPlayer());
		}
	}
	
	/**
	 * @param ServerCommandEvent $event
	 */
	public function onServerCommandProcess(ServerCommandEvent $event){
		$command = strtolower(substr($event->getCommand(), 0, 8));
		if($command === "save-all"){
			$this->onCommandProcess($event->getSender());
		}
	}
	
	public function onCommandProcess(CommandSender $sender){
		$command = $this->getServer()->getCommandMap()->getCommand("save-all");
		if($command instanceof Command){
			if($command->testPermissionSilent($sender)){
				$this->save();
				$sender->sendMessage("[EconomyAPI] Saved money data.");
			}
		}
	}
	
	/**
	 * @return string
	*/
	public function __toString(){
		return "EconomyAPI (total accounts: " . count($this->money) . ")";
	}
}
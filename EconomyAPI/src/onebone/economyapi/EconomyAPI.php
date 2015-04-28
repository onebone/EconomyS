<?php

namespace onebone\economyapi;

use onebone\economyapi\event\debt\DebtChangedEvent;
use onebone\economyapi\event\money\MoneyChangedEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Utils;

use onebone\economyapi\event\money\AddMoneyEvent;
use onebone\economyapi\event\money\ReduceMoneyEvent;
use onebone\economyapi\event\money\SetMoneyEvent;
use onebone\economyapi\event\account\CreateAccountEvent;
use onebone\economyapi\event\debt\AddDebtEvent;
use onebone\economyapi\event\debt\ReduceDebtEvent;
use onebone\economyapi\event\bank\AddMoneyEvent as BankAddMoneyEvent;
use onebone\economyapi\event\bank\ReduceMoneyEvent as BankReduceMoneyEvent;
use onebone\economyapi\event\bank\MoneyChangedEvent as BankMoneyChangedEvent;
use onebone\economyapi\database\DataConverter;
use onebone\economyapi\task\SaveTask;

class EconomyAPI extends PluginBase implements Listener{

	/**
	 * @var EconomyAPI
	 */
	private static $obj = null;
	private $path;
	private $money, $bank;
	/**
	 * @var Config
	 */
	private $config;
	/**
	 * @var Config
	 */
	private $command;

	private $list;

	private $langRes, $playerLang; // language system related
	
	private $monetaryUnit;
	
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
	
	const CURRENT_DATABASE_VERSION = 0x02;
	
	private $langList = array(
		"def" => "Default",
		"en" => "English",
		"ko" => "한국어",
		"it" => "Italiano",
		"ch" => "中文",
		"id" => "Bahasa Indonesia",
		"user-define" => "User Defined"
	);

	public static function getInstance(){
		return self::$obj;
	}
	
	public function onLoad(){
		self::$obj = $this;
		
		$this->path = $this->getDataFolder();

		$this->money = array();
		$this->bank = array();

		$this->playerLang = array();
		$this->langRes = array();
	}
	
	public function onEnable(){
		@mkdir($this->path);
		
		$this->createConfig();
		$this->scanResources();
		
		file_put_contents($this->path."ReadMe.txt", $this->readResource("ReadMe.txt"));
		if(!is_file($this->path."PlayerLang.dat")){
			file_put_contents($this->path."PlayerLang.dat", serialize(array()));
		}
		
		$this->playerLang = unserialize(file_get_contents($this->path."PlayerLang.dat"));

		if(!isset($this->playerLang["console"])){
			$this->getLangFile();
		}
		$cmds = array(
			"setmoney" => "onebone\\economyapi\\commands\\SetMoneyCommand",
			"seemoney" => "onebone\\economyapi\\commands\\SeeMoneyCommand",
			"mymoney" => "onebone\\economyapi\\commands\\MyMoneyCommand",
			"pay" => "onebone\\economyapi\\commands\\PayCommand",
			"givemoney" => "onebone\\economyapi\\commands\\GiveMoneyCommand",
			"takedebt" => "onebone\\economyapi\\commands\\TakeDebtCommand",
			"topmoney" => "onebone\\economyapi\\commands\\TopMoneyCommand",
			"setlang" => "onebone\\economyapi\\commands\\SetLangCommand",
			"takemoney" => "onebone\\economyapi\\commands\\TakeMoneyCommand",
			"mydebt" => "onebone\\economyapi\\commands\\MyDebtCommand",
			"returndebt" => "onebone\\economyapi\\commands\\ReturnDebtCommand",
			"mystatus" => "onebone\\economyapi\\commands\\MyStatusCommand"
		);
		$commandMap = $this->getServer()->getCommandMap();
		foreach($cmds as $key => $cmd){
			foreach($this->command->get($key) as $c){
				$commandMap->register("economyapi", new $cmd($this, $c));
			}
		}
		
		// getServer().getPluginManager().registerEvents(this, this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->convertData();
		$moneyConfig = new Config($this->path."Money.yml", Config::YAML, array(
			"version" => 2,
			"money" => [],
			"debt" => []
		));
		$bankConfig = new Config($this->path."Bank.yml", Config::YAML);
		
		if($moneyConfig->get("version")< self::CURRENT_DATABASE_VERSION){
			$converter = new DataConverter($this->path."Money.yml", $this->path."Bank.yml");
			$result = $converter->convertData(self::CURRENT_DATABASE_VERSION);
			if($result !== false){
				$this->getLogger()->info("Converted data into new database. Database version : ".self::CURRENT_DATABASE_VERSION);
			}
			$moneyConfig = new Config($this->path."Money.yml", Config::YAML);
			$bankConfig = new Config($this->path."Bank.yml", Config::YAML);
		}
		$this->money = $moneyConfig->getAll();
		$this->bank = $bankConfig->getAll();
		
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
				
				$lastest = Utils::getURL("https://raw.githubusercontent.com/onebone/EconomyS/master/EconomyAPI/plugin.yml");
				
				$desc = \yaml_parse($lastest);
				
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
		if(is_file($this->path."MoneyData.yml")){
			$data = (new Config($this->path."MoneyData.yml", Config::YAML))->getAll();
			$saveData = array();
			foreach($data as $player => $money){
				$saveData["money"][$player] = round($money["money"], 2);
				$saveData["debt"][$player] = round($money["debt"], 2);
				++$cnt;
			}
			@unlink($this->path."MoneyData.yml");
			$moneyConfig = new Config($this->path."Money.yml", Config::YAML);
			$moneyConfig->setAll($saveData);
			$moneyConfig->save();
		}
		if(is_file($this->path."BankData.yml")){
			$data = (new Config($this->path."BankData.yml", Config::YAML))->getAll();
			$saveData = array();
			foreach($data as $player => $money){
				$saveData["money"][$player] = round($money["money"], 2);
				++$cnt;
			}
			@unlink($this->path."BankData.yml");
			$bankConfig = new Config($this->path."Bank.yml", Config::YAML);
			$bankConfig->setAll($saveData);
			$bankConfig->save();
		}
		if($cnt > 0){
			$this->getLogger()->info(TextFormat::AQUA."Converted $cnt data(m) into new format");
		}
	}
	
	private function createConfig(){
		$this->config = new Config($this->path."economy.properties", Config::PROPERTIES, yaml_parse($this->readResource("config.yml")));
		$this->command = new Config($this->path."command.yml", Config::YAML, yaml_parse($this->readResource("command.yml")));
	}
	
	private function scanResources(){
		foreach($this->getResources() as $resource){
			$s = explode(\DIRECTORY_SEPARATOR, $resource);
			$res = $s[count($s) - 1];
			if(substr($res, 0, 5) === "lang_"){
				$this->langRes[substr($res, 5, -5)] = get_object_vars(json_decode($this->readResource($res)));
			}
		}
		$this->langRes["user-define"] = (new Config($this->path."language.properties", Config::PROPERTIES, $this->langRes["def"]))->getAll();
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
			$this->getLogger()->info(TextFormat::GREEN.$this->getMessage("language-set", "console", array($this->langList[$lang], "%2", "%3", "%4")));
		}else{
			$this->playerLang["console"] = "def";
			$this->playerLang["rcon"] = "def";
			$this->getLogger()->info(TextFormat::GREEN.$this->getMessage("language-set", "console", array($this->langList[$lang], "%2", "%3", "%4")));
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
		if($amount <= 0){
			return self::RET_INVALID;
		}
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		$amount = round($amount, 2);
		if(isset($this->money["debt"][$player])){
			$debt = $this->money["debt"][$player];
			
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
			$this->money["debt"][$player] += $amount;

			$this->getServer()->getPluginManager()->callEvent(new DebtChangedEvent($this, $player, $this->money["debt"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
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
	public function reduceDebt($player, $amount, $force = false, $issuer = "external"){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$amount = round($amount, 2);
		if(isset($this->money["debt"][$player])){
			$debt = $this->money["debt"][$player];
			$money = $this->money["money"][$player];
			if($amount <= 0 or $debt < $amount or $money < $amount){
				return self::RET_INVALID;
			}
			$ev = new ReduceDebtEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->money["debt"][$player] -= $amount;
			
			$this->getServer()->getPluginManager()->callEvent(new DebtChangedEvent($this, $player, $this->money["debt"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
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
	public function addBankMoney($player, $amount, $force = false, $issuer = "external"){
		if($amount <= 0){
			return self::RET_INVALID;
		}
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$amount = round($amount, 2);
		if(isset($this->bank["money"][$player])){
			$ev = new BankAddMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->bank["money"][$player] += $amount;
			
			$this->getServer()->getPluginManager()->callEvent(new BankMoneyChangedEvent($this, $player, $this->bank["money"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
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
	public function reduceBankMoney($player, $amount, $force = false, $issuer = "external"){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$amount = round($amount, 2);
		if(isset($this->bank["money"][$player])){
			if($amount <= 0 or $amount > $this->bank["money"][$player]){
				return self::RET_INVALID;
			}
			$ev = new BankReduceMoneyEvent($this, $player, $amount, $issuer);
			$this->getServer()->getPluginManager()->callEvent($ev);
			if($force === false and $ev->isCancelled()){
				return self::RET_CANCELLED;
			}
			$this->bank["money"][$player] -= $amount;
			
			$this->getServer()->getPluginManager()->callEvent(new BankMoneyChangedEvent($this, $player, $this->bank["money"][$player], $issuer));
			return self::RET_SUCCESS;
		}else{
			return self::RET_NOT_FOUND;
		}
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
		return $this->bank;
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
	public function getMessage($key, $player = "console", array $value = array("%1", "%2", "%3", "%4")){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(isset($this->playerLang[$player]) and isset($this->langRes[$this->playerLang[$player]][$key])){
			return str_replace(array("%MONETARY_UNIT%", "%1", "%2", "%3", "%4"), array($this->monetaryUnit, $value[0], $value[1], $value[2], $value[3]), $this->langRes[$this->playerLang[$player]][$key]);
		}elseif(isset($this->langRes["def"][$key])){
			return str_replace(array("%MONETARY_UNIT%", "%1", "%2", "%3", "%4"), array($this->monetaryUnit, $value[0], $value[1], $value[2], $value[3]), $this->langRes["def"][$key]);
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
	 * @param bool|float $default_debt
	 * @param bool|float $default_bank_money
	 * @param bool $force
	 *
	 * @return boolean
	 */
	public function createAccount($player, $default_money = false, $default_debt = false, $default_bank_money = false, $force = false){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->money["money"][$player])){
			$this->getServer()->getPluginManager()->callEvent(($ev = new CreateAccountEvent($this, $player, $default_money, $default_debt, $default_bank_money, "EconomyAPI")));
			if(!$ev->isCancelled() and $force === false){
				$this->money["money"][$player] = ($default_money === false ? $this->config->get("default-money") : $default_money);
				$this->money["debt"][$player] = ($default_debt === false ? $this->config->get("default-debt") : $default_debt);
				$this->bank["money"][$player] = ($default_bank_money === false ? $this->config->get("default-bank-money") : $default_bank_money);
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
			$this->money["debt"][$player] = null;
			$this->bank["money"][$player] = null;
			unset($this->money["money"][$player], $this->money["debt"][$player], $this->bank["money"][$player]);
			
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
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		return isset($this->bank["money"][$player]);
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
	 * @ deprecated
	 *
	 * @param Player|string $player
	 *
	 * @return boolean|float
	*/
	public function myDebt($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->money["debt"][$player])){
			return false;
		}
		return $this->money["debt"][$player];
	}
	
	/**
	 * @deprecated
	 *
	 * @param Player|string $player
	 *
	 * @return boolean|float
	*/
	public function myBankMoney($player){ // To identify the result, use '===' operator
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->bank["money"][$player])){
			return false;
		}
		return $this->bank["money"][$player];
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
		$moneyConfig = new Config($this->path."Money.yml", Config::YAML);
		$bankConfig = new Config($this->path."Bank.yml", Config::YAML);
		$moneyConfig->setAll($this->money);
		$moneyConfig->save();
		$bankConfig->setAll($this->bank);
		$bankConfig->save();
		file_put_contents($this->path."PlayerLang.dat", serialize($this->playerLang));
	}
	
	public function onLoginEvent(PlayerLoginEvent $event){
		$username = strtolower($event->getPlayer()->getName());
		if(!isset($this->money["money"][$username])){
			$this->getServer()->getPluginManager()->callEvent(($ev = new CreateAccountEvent($this, $username, $this->config->get("default-money"), $this->config->get("default-debt"), null, "EconomyAPI")));
			$this->money["money"][$username] = round($ev->getDefaultMoney(), 2);
			$this->money["debt"][$username] = round($ev->getDefaultDebt(), 2);
		}
		if(!isset($this->bank["money"][$username])){
			$this->getServer()->getPluginManager()->callEvent(($ev = new CreateAccountEvent($this, $username, null, null, $this->config->get("default-bank-money"), "EconomyAPI")));
			$this->bank["money"][$username] = round($ev->getDefaultBankMoney(), 2);
		}
		if(!isset($this->playerLang[$username])){
			$this->setLang($this->config->get("default-lang"), $username);
		}
	}
	
	/**
	 * @return string
	*/
	public function __toString(){
		return "EconomyAPI (accounts:".count($this->money).", bank: ".count($this->money).")";
	}
}
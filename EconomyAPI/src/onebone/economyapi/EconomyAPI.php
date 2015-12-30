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

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\utils\Utils;
use pocketmine\utils\TextFormat;

use onebone\economyapi\provider\Provider;
use onebone\economyapi\provider\YamlProvider;
use onebone\economyapi\provider\MySQLProvider;
use onebone\economyapi\event\money\SetMoneyEvent;
use onebone\economyapi\event\money\ReduceMoneyEvent;
use onebone\economyapi\event\money\AddMoneyEvent;
use onebone\economyapi\event\account\CreateAccountEvent;

class EconomyAPI extends PluginBase implements Listener{
	const API_VERSION = 3;
	const PACKAGE_VERSION = "5.7";

	const RET_NO_ACCOUNT = -3;
	const RET_CANCELLED = -2;
	const RET_NOT_FOUND = -1;
	const RET_INVALID = 0;
	const RET_SUCCESS = 1;

	private static $instance = null;

	/** @var Provider */
	private $provider;

	private $langList = [
		"def" => "Default",
		"user-define" => "User Defined",
		"ch" => "简体中文",
		"cs" => "Čeština",
		"en" => "English",
		"fr" => "Français",
		"id" => "Bahasa Indonesia",
		"it" => "Italiano",
		"jp" => "日本語",
		"ko" => "한국어",
		"nl" => "Nederlands",
		"ru" => "Русский",
		"zh" => "繁體中文",
	];
	private $lang = [], $playerLang = [];

	public function getCommandMessage($command, $lang = false){
		if($lang === false){
			$lang = $this->getConfig()->get("default-lang");
		}
		$command = strtolower($command);
		if(isset($this->lang[$lang]["commands"][$command])){
			return $this->lang[$lang]["commands"][$command];
		}else{
			return $this->lang["def"]["commands"][$command];
		}
	}

	public function getMessage($key, $params = [], $player = "console"){
		$player = strtolower($player);
		if(isset($this->lang[$this->playerLang[$player]][$key])){
			return $this->replaceParameters($this->lang[$this->playerLang[$player]][$key], $params);
		}elseif(isset($this->lang["def"][$key])){
			return $this->replaceParameters($this->lang["def"][$key], $params);
		}
		return "Language matching key \"$key\" does not exist.";
	}

	public function getMonetaryUnit() : string{
		return $this->getConfig()->get("monetary-unit");
	}

	/**
	 * @return array
	 */
	public function getAllMoney() : array{
		return $this->provider->getAll();
	}

	/**
	 * @param string|Player		$player
	 * @param float				$defaultMoney
	 * @param bool				$force
	 *
	 * @return bool
	 */
	public function createAccount($player, $defaultMoney = false, bool $force = false) : bool{
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(!$this->provider->accountExists($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new CreateAccountEvent($this, $player, $defaultMoney, "none"));
			if(!$ev->isCancelled() or $force === true){
				$this->provider->createAccount($player, $ev->getDefaultMoney());
			}
		}
		return false;
	}

	/**
	 * @param Player|string		$player
	 *
	 * @return float|bool
	 */
	public function myMoney($player){
		return $this->provider->getMoney($player);
	}

	/**
	 * @param string|Player 	$player
	 * @param float 			$amount
	 * @param bool				$force
	 * @param string			$issuer
	 *
	 * @return int
	 */
	public function setMoney($player, $amount, bool $force = false, string $issuer = "none") : int{
		if($amount < 0){
			return self::RET_INVALID;
		}

		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if($this->provider->accountExists($player)){
			$amount = round($amount, 2);
			if($amount > $this->getConfig()->get("max-money")){
				return self::RET_INVALID;
			}

			$this->getServer()->getPluginManager()->callEvent($ev = new SetMoneyEvent($this, $player, $amount, $issuer));
			if(!$ev->isCancelled() or $force === true){
				$this->provider->setMoney($player, $amount);
				$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $amount, $issuer));
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

	/**
	 * @param string|Player 	$player
	 * @param float 			$amount
	 * @param bool				$force
	 * @param string			$issuer
	 *
	 * @return int
	 */
	public function addMoney($player, $amount, bool $force = false, $issuer = "none") : int{
		if($amount < 0){
			return self::RET_INVALID;
		}
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(($money = $this->provider->getMoney($player)) !== false){
			$amount = round($amount, 2);
			if($money + $amount > $this->getConfig()->get("max-money")){
				return self::RET_INVALID;
			}

			$this->getServer()->getPluginManager()->callEvent($ev = new AddMoneyEvent($this, $player, $amount, $issuer));
			if(!$ev->isCancelled() or $force === true){
				$this->provider->addMoney($player, $amount);
				$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $amount + $money, $issuer));
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

	/**
	 * @param string|Player 	$player
	 * @param float 			$amount
	 * @param bool				$force
	 * @param string			$issuer
	 *
	 * @return int
	 */
	public function reduceMoney($player, $amount, bool $force = false, $issuer = "none") : int{
		if($amount < 0){
			return self::RET_INVALID;
		}
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(($money = $this->provider->getMoney($player)) !== false){
			$amount = round($amount, 2);
			if($money - $amount < 0){
				return self::RET_INVALID;
			}

			$this->getServer()->getPluginManager()->callEvent($ev = new ReduceMoneyEvent($this, $player, $amount, $issuer));
			if(!$ev->isCancelled() or $force === true){
				$this->provider->reduceMoney($player, $amount);
				$this->getServer()->getPluginManager()->callEvent(new MoneyChangedEvent($this, $player, $money - $amount, $issuer));
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

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
		if(!$this->getDataFolder()){
			mkdir($this->getDataFolder());
		}
		if(!is_file($this->getDataFolder()."PlayerLang.dat")){
			file_put_contents($this->getDataFolder()."PlayerLang.dat", serialize([]));
		}
		$this->playerLang = unserialize(file_get_contents($this->getDataFolder()."PlayerLang.dat"));

		$this->saveDefaultConfig();
		$this->initialize();
	}

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();

		if(!isset($this->playerLang[strtolower($player->getName())])){
			$this->playerLang[strtolower($player->getName())] = $this->getConfig()->get("default-lang");
		}
		if(!$this->provider->accountExists($player)){
			$this->getLogger()->debug("Account of '".$player->getName()."' is not found. Creating account...");
			$this->createAccount($player);
		}
	}

	public function onDisable(){
		if($this->provider instanceof Provider){
			$this->provider->close();
		}
	}

	private function replaceParameters($message, $params = []){
		$search = ["%MONETARY_UNIT%"];
		$replace = [$this->getMonetaryUnit()];
		for($i = 0; $i < count($params); $i++){
			$search[] = "%".($i + 1);
			$replace[] = $params[$i];
		}

		$colors = [
			"BLACK" => "0",
			"DARK_BLUE" => "1",
			"DARK_GREEN" => "2",
			"DARK_AQUA" => "3",
			"DARK_RED" => "4",
			"DARK_PURPLE" => "5",
			"GOLD" => "6",
			"GRAY" => "7",
			"DARK_GRAY" => "8",
			"BLUE" => "9",
			"GREEN" => "a",
			"AQUA" => "b",
			"RED" => "c",
			"LIGHT_PURPLE" => "d",
			"YELLOW" => "e",
			"WHITE" => "f",
			"OBFUSCATED" => "k",
			"BOLD" => "l",
			"STRIKETHROUGH" => "m",
			"UNDERLINE" => "n",
			"ITALIC" => "o",
			"RESET" => "r"
		];
		foreach($colors as $color => $code){
			$search[] = "&".$color;
			$replace[] = TextFormat::ESCAPE.$code;
		}

		return str_replace($search, $replace, $message);
	}

	private function initialize(){
		if($this->getConfig()->get("check-update")){
			$this->checkUpdate();
		}
		switch(strtolower($this->getConfig()->get("provider"))){
			case "yaml":
			$this->provider = new YamlProvider($this->getDataFolder()."Money.yml");
			break;
			case "mysql":
			$this->provider = new MySQLProvider($this->getConfig()->get("provider-settings"));
			break;
			default:
			$this->getLogger()->critical("Invalid database was given.");
			return false;
		}
		$this->initializeLanguage();
		$this->getLogger()->notice("Database provider was set to: ".$this->provider->getName());
		$this->registerCommands();
	}

	private function checkUpdate(){
		try{
			$info = json_decode(Utils::getURL($this->getConfig()->get("update-host")."?version=".$this->getDescription()->getVersion()."&package_version=".self::PACKAGE_VERSION), true);
			if(!isset($info["status"]) or $info["status"] !== true){
				$this->getLogger()->notice("Something went wrong on update server.");
				return false;
			}
			if($info["update-available"] === true){
				$this->getLogger()->notice("Server says new version (".$info["new-version"].") of EconomyS is out. Check it out at ".$info["download-address"]);
			}
			$this->getLogger()->notice($info["notice"]);
			return true;
		}catch(\Throwable $e){
			$this->getLogger()->logException($e);
			return false;
		}
	}

	private function registerCommands(){
		$map = $this->getServer()->getCommandMap();

		$commands = [
			"mymoney" => "\\onebone\\economyapi\\command\\MyMoneyCommand"
		];
		foreach($commands as $cmd => $class){
			$map->register("economyapi", new $class($this));
		}
	}

	private function initializeLanguage(){
		foreach($this->getResources() as $resource){
			if($resource->isFile() and substr(($filename = $resource->getFilename()), 0, 5) === "lang_"){
				$this->lang[substr($filename, 5, -5)] = json_decode(file_get_contents($resource->getPathname()), true);
			}
		}
		$this->lang["user-define"] = (new Config($this->getDataFolder()."messages.yml", Config::YAML, $this->lang["def"]))->getAll();
	}
}

<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2019  onebone <jyc00410@gmail.com>
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

use onebone\economyapi\command\GiveMoneyCommand;
use onebone\economyapi\command\MyMoneyCommand;
use onebone\economyapi\command\MyStatusCommand;
use onebone\economyapi\command\PayCommand;
use onebone\economyapi\command\SeeMoneyCommand;
use onebone\economyapi\command\SetLangCommand;
use onebone\economyapi\command\SetMoneyCommand;
use onebone\economyapi\command\TakeMoneyCommand;
use onebone\economyapi\command\TopMoneyCommand;
use onebone\economyapi\config\PluginConfig;
use onebone\economyapi\currency\CurrencyDollar;
use onebone\economyapi\currency\CurrencyWon;
use onebone\economyapi\event\account\CreateAccountEvent;
use onebone\economyapi\event\money\AddMoneyEvent;
use onebone\economyapi\event\money\MoneyChangedEvent;
use onebone\economyapi\event\money\ReduceMoneyEvent;
use onebone\economyapi\event\money\SetMoneyEvent;
use onebone\economyapi\provider\DummyUserProvider;
use onebone\economyapi\provider\UserProvider;
use onebone\economyapi\provider\YamlUserProvider;
use onebone\economyapi\task\SaveTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use Throwable;

class EconomyAPI extends PluginBase implements Listener {
	const API_VERSION = 4;
	const PACKAGE_VERSION = "6.0";

	const RET_NO_ACCOUNT = -3;
	const RET_CANCELLED = -2;
	/**
	 * @deprecated No longer used by internal code.
	 * @deprecated It will be removed in a future release.
	 */
	const RET_NOT_FOUND = -1;
	const RET_INVALID = 0;
	const RET_SUCCESS = 1;

	const CURRENCY_CONFIG_DEFAULT = 0;
	const CURRENCY_CONFIG_EXCHANGE = 1;
	const CURRENCY_CONFIG_MAX = 2;

	private static $instance = null;

	/** @var PluginConfig $pluginConfig */
	private $pluginConfig;

	/** @var Currency[] */
	private $currencies = [];
	/** @var Currency */
	private $defaultCurrency;
	private $currencyConfig;

	/** @var UserProvider */
	private $provider;

	private $langList = [
		"def"         => "Default",
		"user-define" => "User Defined",
		"ch"          => "简体中文",
		"cs"          => "Čeština",
		"en"          => "English",
		"fr"          => "Français",
		"id"          => "Bahasa Indonesia",
		"it"          => "Italiano",
		"ja"          => "日本語",
		"ko"          => "한국어",
		"nl"          => "Nederlands",
		"ru"          => "Русский",
		"zh"          => "繁體中文",
	];
	private $lang = [];

	/**
	 * @return EconomyAPI
	 */
	public static function getInstance() {
		return self::$instance;
	}

	/**
	 * @param string $command
	 * @param string|bool $lang
	 *
	 * @return array
	 */
	public function getCommandMessage(string $command, $lang = false): array {
		if($lang === false) {
			$lang = $this->pluginConfig->getDefaultLanguage();
		}
		$command = strtolower($command);
		if(isset($this->lang[$lang]["commands"][$command])) {
			return $this->lang[$lang]["commands"][$command];
		}else{
			return $this->lang["def"]["commands"][$command];
		}
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @param string $player
	 *
	 * @return string
	 */
	public function getMessage(string $key, array $params = [], string $player = "console"): string {
		$player = strtolower($player);

		$lang = $this->provider->getLanguage($player);
		if(isset($this->lang[$lang][$key])) {
			$lang = $this->provider->getLanguage($player);

			return $this->replaceParameters($this->lang[$lang][$key], $params);
		}elseif(isset($this->lang["def"][$key])) {
			return $this->replaceParameters($this->lang["def"][$key], $params);
		}
		return "Language matching key \"$key\" does not exist.";
	}

	private function replaceParameters($message, $params = []) {
		$search = ["%MONETARY_UNIT%"];
		$replace = [$this->defaultCurrency->getUnit()];

		for ($i = 0; $i < count($params); $i++) {
			$search[] = "%" . ($i + 1);
			$replace[] = $params[$i];
		}

		$colors = [
			"0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
			"a", "b", "c", "d", "e", "f", "k", "l", "m", "n", "o", "r"
		];
		foreach($colors as $code) {
			$search[] = "&" . $code;
			$replace[] = TextFormat::ESCAPE . $code;
		}

		return str_replace($search, $replace, $message);
	}

	/**
	 * @return PluginConfig
	 */
	public function getPluginConfig(): PluginConfig {
		return $this->pluginConfig;
	}

	public function getMonetaryUnit(): string {
		return $this->defaultCurrency->getUnit();
	}

	public function setPlayerLanguage(string $player, string $language): bool {
		$player = strtolower($player);
		$language = strtolower($language);
		if(isset($this->lang[$language])) {
			return $this->provider->setLanguage($player, $language);
		}
		return false;
	}

	public function hasCurrency(string $id) {
		return isset($this->currencies[$id]);
	}

	/**
	 * @return array
	 */
	public function getAllMoney(): array {
		return $this->defaultCurrency->getProvider()->getAll();
	}

	/**
	 * @param string|Player $player
	 *
	 * @return bool
	 */
	public function accountExists($player): bool {
		return $this->defaultCurrency->getProvider()->accountExists($player);
	}

	/**
	 * @param Player|string $player
	 *
	 * @return float|bool
	 */
	public function myMoney($player) {
		return $this->defaultCurrency->getProvider()->getMoney($player);
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function setMoney($player, float $amount, bool $force = false, string $issuer = "none"): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		if($this->defaultCurrency->getProvider()->accountExists($player)) {
			$amount = round($amount, 2);
			// TODO configuration for max money of each currency
			/*if($amount > $this->defaultCurrency->getMaxMoney()) {
				return self::RET_INVALID;
			}*/

			$ev = new SetMoneyEvent($this, $player, $amount, $issuer);
			$ev->call();
			if(!$ev->isCancelled() or $force === true) {
				$this->defaultCurrency->getProvider()->setMoney($player, $amount);
				(new MoneyChangedEvent($this, $player, $amount, $issuer))->call();
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function addMoney($player, float $amount, bool $force = false, $issuer = "none"): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(($money = $this->defaultCurrency->getProvider()->getMoney($player)) !== false) {
			$amount = round($amount, 2);
			/*if($money + $amount > $this->pluginConfig->getMaxMoney()) {
				return self::RET_INVALID;
			}*/

			$ev = new AddMoneyEvent($this, $player, $amount, $issuer);
			$ev->call();
			if(!$ev->isCancelled() or $force === true) {
				$this->defaultCurrency->getProvider()->addMoney($player, $amount);
				(new MoneyChangedEvent($this, $player, $amount + $money, $issuer))->call();
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param bool $force
	 * @param string $issuer
	 *
	 * @return int
	 */
	public function reduceMoney($player, float $amount, bool $force = false, $issuer = "none"): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(($money = $this->defaultCurrency->getProvider()->getMoney($player)) !== false) {
			$amount = round($amount, 2);
			if($money - $amount < 0) {
				return self::RET_INVALID;
			}

			$ev = new ReduceMoneyEvent($this, $player, $amount, $issuer);
			$ev->call();
			if(!$ev->isCancelled() or $force === true) {
				$this->defaultCurrency->getProvider()->reduceMoney($player, $amount);
				(new MoneyChangedEvent($this, $player, $money - $amount, $issuer))->call();
				return self::RET_SUCCESS;
			}
			return self::RET_CANCELLED;
		}
		return self::RET_NO_ACCOUNT;
	}

	public function getDefaultCurrency(): Currency {
		return $this->defaultCurrency;
	}

	/**
	 * @param string|Player $player
	 * @param float|bool $defaultMoney
	 * @param bool $force
	 * @param string|Currency $currency
	 *
	 * @return bool
	 */
	public function createAccount($player, $defaultMoney = false, bool $force = false, $currency = null): bool {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$currency = $this->validateCurrency($currency);

		if(!$currency->getProvider()->accountExists($player)) {
			$defaultMoney = ($defaultMoney === false) ? $currency->getDefaultMoney() : $defaultMoney;

			$ev = new CreateAccountEvent($this, $player, $defaultMoney, "none");
			$ev->call();
			if(!$ev->isCancelled() or $force === true) {
				$currency->getProvider()->createAccount($player, $ev->getDefaultMoney());
			}
		}

		return false;
	}

	public function hasLanguage(string $lang): bool {
		return isset($this->langList[$lang]);
	}

	private function validateCurrency($currency): Currency {
		if(is_string($currency)) {
			$currency = strtolower($currency);
			return $this->currencies[$currency] ?? $this->defaultCurrency;
		}elseif($currency instanceof Currency) {
			return $currency;
		}

		return $this->defaultCurrency;
	}

	public function onLoad() {
		self::$instance = $this;
	}

	public function onEnable() {
		/*
		 * 디폴트 설정 파일을 먼저 생성하게 되면 데이터 폴더 파일이 자동 생성되므로
		 * 'Failed to open stream: No such file or directory' 경고 메시지를 없앨 수 있습니다
		 * - @64FF00
		 *
		 * [추가 옵션]
		 * if(!file_exists($this->dataFolder))
		 *     mkdir($this->dataFolder, 0755, true);
		 */
		$this->saveDefaultConfig();

		$this->initialize();

		if($this->pluginConfig->getAutoSaveInterval() > 0) {
			$this->getScheduler()->scheduleDelayedRepeatingTask(new SaveTask($this), $this->pluginConfig->getAutoSaveInterval() * 1200, $this->pluginConfig->getAutoSaveInterval() * 1200);
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	private function initialize() {
		$this->pluginConfig = new PluginConfig($this->getConfig());

		if($this->pluginConfig->getCheckUpdate()) {
			$this->checkUpdate();
		}

		switch ($this->pluginConfig->getProvider()) {
			case 'yaml':
				$this->provider = new YamlUserProvider($this);
				break;
			default:
				$this->provider = new DummyUserProvider();
				$this->getLogger()->warning('Invalid data provider given.');
				break;
		}

		$this->getLogger()->info('User provider was set to: ' . $this->provider->getName());

		$currencies = [
			'dollar' => new CurrencyDollar($this),
			'won'    => new CurrencyWon($this)
		];

		foreach($currencies as $currencyName => $currencyClass) {
			$this->registerCurrency($currencyName, $currencyClass);
		}

		$default = $this->pluginConfig->getDefaultCurrency();
		foreach($this->currencies as $key => $currency) {
			if($key === $default) {
				$this->defaultCurrency = $currency;

				$this->getLogger()->info('Default currency is set to: ' . $currency->getName());
				break;
			}
		}

		$this->parseCurrencies();
		$this->initializeLanguage();
		$this->registerCommands();
	}

	private function checkUpdate() {
		try{
			$info = json_decode(Internet::getURL($this->pluginConfig->getUpdateHost() . "?version=" . $this->getDescription()->getVersion() . "&package_version=" . self::PACKAGE_VERSION), true);
			if(!isset($info["status"]) or $info["status"] !== true) {
				$this->getLogger()->notice("Something went wrong on update server.");
				return false;
			}
			if($info["update-available"] === true) {
				$this->getLogger()->notice("Server says new version (" . $info["new-version"] . ") of EconomyS is out. Check it out at " . $info["download-address"]);
			}
			$this->getLogger()->notice($info["notice"]);
			return true;
		}catch(Throwable $e) {
			$this->getLogger()->logException($e);
			return false;
		}
	}

	public function registerCurrency(string $id, Currency $currency) {
		$id = strtolower($id);

		if(isset($this->currencies[$id])) {
			return false;
		}

		$this->currencies[$id] = $currency;
		return true;
	}

	public function getCurrency(string $id): ?Currency {
		return $this->currencies[$id] ?? null;
	}

	private function parseCurrencies() {
		$this->currencyConfig = [];

		$currencies = $this->pluginConfig->getCurrencies();
		foreach($currencies as $key => $data) {
			$exchange = $data['exchange'] ?? [];
			foreach($exchange as $target => $value) {
				if(count($value) !== 2 or !is_float($value[0]) or !is_float($value[1])) {
					$this->getLogger()->warning("Currency exchange rate for $key to $target is not valid. It will be excluded.");
					unset($exchange[$target]);
				}
			}

			$this->currencyConfig[$key] = [
				self::CURRENCY_CONFIG_DEFAULT   => $data['default'] ?? null,
				self::CURRENCY_CONFIG_EXCHANGE  => $data['exchange'] ?? [],
				self::CURRENCY_CONFIG_MAX       => $data['max'] ?? 0
			];
		}
	}

	private function initializeLanguage() {
		foreach($this->getResources() as $resource) {
			if($resource->isFile() and substr(($filename = $resource->getFilename()), 0, 5) === "lang_") {
				$this->lang[substr($filename, 5, -5)] = json_decode(file_get_contents($resource->getPathname()), true);
			}
		}
		$this->lang["user-define"] = (new Config($this->getDataFolder() . "messages.yml", Config::YAML, $this->lang["def"]))->getAll();
	}

	private function registerCommands() {
		$map = $this->getServer()->getCommandMap();

		$commands = [
			new GiveMoneyCommand($this),
			new MyMoneyCommand($this),
			new MyStatusCommand($this),
			new PayCommand($this),
			new SeeMoneyCommand($this),
			new SetLangCommand($this),
			new SetMoneyCommand($this),
			new TakeMoneyCommand($this),
			new TopMoneyCommand($this)
		];

		foreach($commands as $command) {
			$map->register("economyapi", $command);
		}
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();

		if(!$this->defaultCurrency->getProvider()->accountExists($player)) {
			$this->getLogger()->debug("UserInfo of '" . $player->getName() . "' is not found. Creating account...");
			$this->createAccount($player, false, true);
		}
	}

	public function onDisable() {
		foreach($this->currencies as $currency) {
			$currency->close();
		}
	}

	public function saveAll() {
		foreach($this->currencies as $currency) {
			$currency->save();
		}
	}
}

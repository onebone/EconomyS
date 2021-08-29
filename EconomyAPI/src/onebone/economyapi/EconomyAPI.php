<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2021  onebone <me@onebone.me>
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

use AssertionError;
use InvalidArgumentException;
use onebone\economyapi\command\EconomyCommand;
use onebone\economyapi\command\GiveMoneyCommand;
use onebone\economyapi\command\MyMoneyCommand;
use onebone\economyapi\command\MyStatusCommand;
use onebone\economyapi\command\PayCommand;
use onebone\economyapi\command\SeeMoneyCommand;
use onebone\economyapi\command\SetMoneyCommand;
use onebone\economyapi\command\TakeMoneyCommand;
use onebone\economyapi\command\TopMoneyCommand;
use onebone\economyapi\currency\Currency;
use onebone\economyapi\currency\CurrencyConfig;
use onebone\economyapi\currency\CurrencySelector;
use onebone\economyapi\currency\SimpleCurrencySelector;
use onebone\economyapi\event\Issuer;
use onebone\economyapi\currency\CurrencyDollar;
use onebone\economyapi\currency\CurrencyWon;
use onebone\economyapi\event\account\CreateAccountEvent;
use onebone\economyapi\event\money\AddMoneyEvent;
use onebone\economyapi\event\money\MoneyChangedEvent;
use onebone\economyapi\event\money\ReduceMoneyEvent;
use onebone\economyapi\event\money\SetMoneyEvent;
use onebone\economyapi\internal\CurrencyHolder;
use onebone\economyapi\util\Promise;
use onebone\economyapi\util\Replacer;
use onebone\economyapi\provider\DummyProvider;
use onebone\economyapi\provider\user\DummyUserProvider;
use onebone\economyapi\provider\MySQLProvider;
use onebone\economyapi\provider\Provider;
use onebone\economyapi\provider\user\UserProvider;
use onebone\economyapi\provider\YamlProvider;
use onebone\economyapi\provider\user\YamlUserProvider;
use onebone\economyapi\task\SaveTask;
use onebone\economyapi\util\PluginConfig;
use onebone\economyapi\util\Transaction;
use onebone\economyapi\util\TransactionResult;
use pocketmine\command\CommandSender;
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

	/**
	 * @since 4
	 * Currency provider has failed to process the request
	 */
	const RET_PROVIDER_FAILURE = -5;
	// RET_INVALID_CURRENCY: Player could not use currency at that time
	const RET_INVALID_CURRENCY = -4;
	// RET_NO_ACCOUNT: Account associated with given currency does not exist
	const RET_NO_ACCOUNT = -3;
	// RET_CANCELLED: Balance manipulation was rejected by external plugin
	const RET_CANCELLED = -2;
	/**
	 * @deprecated No longer used by internal code.
	 * @deprecated It will be removed in a future release.
	 */
	const RET_NOT_FOUND = -1;
	// RET_UNAVAILABLE: Balance cannot be manipulated because balance will go under or over the limitation of plugin
	const RET_UNAVAILABLE = -1;
	// RET_INVALID: Given value is not valid
	const RET_INVALID = 0;
	// RET_SUCCESS: Succeeded with no problem
	const RET_SUCCESS = 1;

	/** @internal */
	private const RET_VALID = 1;

	const FALLBACK_LANGUAGE = "en";

	private static $instance = null;

	/** @var PluginConfig $pluginConfig */
	private $pluginConfig;

	/** @var CurrencyHolder[] */
	private $currencies = [];
	/** @var CurrencyHolder */
	private $defaultCurrency;

	/** @var CurrencySelector */
	private $currencySelector = null;

	/** @var UserProvider */
	private $provider;

	const USER_DEFINED = 'user-define';

	private $lang = [];

	/**
	 * Returns instance of EconomyAPI. Should be called after onLoad() phase.
	 * @return EconomyAPI
	 */
	public static function getInstance(): EconomyAPI {
		return self::$instance;
	}

	/**
	 * @param string $command
	 *
	 * @return array
	 */
	public function getCommandMessage(string $command): array {
		$lang = $this->pluginConfig->getDefaultLanguage();

		$command = strtolower($command);
		if(isset($this->lang[$lang]["commands"][$command])) {
			return $this->lang[$lang]["commands"][$command];
		}else{
			return $this->lang[self::FALLBACK_LANGUAGE]["commands"][$command];
		}
	}

	public function hasLanguage(string $lang): bool {
		return isset($this->lang[$lang]);
	}

	public function getLanguages(): array {
		return array_keys($this->lang);
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @param CommandSender|string $sender
	 *
	 * @return string
	 */
	public function getMessage(string $key, $sender, array $params = []): string {
		if($sender instanceof CommandSender) {
			$sender = $sender->getName();
		}
		$sender = strtolower($sender);

		$lang = $this->provider->getLanguage($sender);
		if(isset($this->lang[$lang][$key])) {
			$lang = $this->provider->getLanguage($sender);

			return $this->replaceParameters($this->lang[$lang][$key], $params);
		}elseif(isset($this->lang[self::FALLBACK_LANGUAGE][$key])) {
			return $this->replaceParameters($this->lang[self::FALLBACK_LANGUAGE][$key], $params);
		}
		return "Language matching key \"$key\" does not exist.";
	}

	private const STATUS_NONE = 0;
	private const STATUS_ESCAPE = 1;
	private const STATUS_PARAMETER = 2;
	private const STATUS_CURRENCY = 3;

	public function replaceParameters($message, $params = []): string {
		$ret = '';

		$len = strlen($message);
		$status = self::STATUS_NONE;
		$chunk = '';

		for($i = 0; $i < $len + 1; $i++) {
			$char = $message[$i] ?? '';

			if($status === self::STATUS_ESCAPE) {
				$ret .= $char;
				$status = self::STATUS_NONE;
				continue;
			}

			if($char === '%') {
				if($status === self::STATUS_ESCAPE) {
					$status = self::STATUS_NONE;
				}else{
					$status = self::STATUS_PARAMETER;
				}
			}else if($char === '\\') {
				$status = self::STATUS_ESCAPE;
			}else if($char === '$') {
				if($status === self::STATUS_PARAMETER and $chunk === '') {
					$status = self::STATUS_CURRENCY;
				}
			}else{
				if(is_numeric($char) and ($status === self::STATUS_PARAMETER or $status === self::STATUS_CURRENCY)) {
					$chunk .= $char;
				}else{
					if(($status === self::STATUS_PARAMETER or $status === self::STATUS_CURRENCY) and $chunk !== '') {
						$id = (int) $chunk;
						$chunk = '';

						$value = $params[$id - 1] ?? '&cnull&f';
						if($value instanceof Replacer) {
							$value = $value->getText();
						}else if($status === self::STATUS_CURRENCY) {
							$value = $this->getMonetaryUnit() . $value;
						}

						$ret .= $value;
					}

					$ret .= $char;
					$status = self::STATUS_NONE;
				}
			}
		}

		return TextFormat::colorize($ret);
	}

	/**
	 * @return PluginConfig
	 */
	public function getPluginConfig(): PluginConfig {
		return $this->pluginConfig;
	}

	public function setCurrencySelector(CurrencySelector $selector) {
		$this->currencySelector = $selector;
	}

	public function getCurrencySelector(): CurrencySelector {
		return $this->currencySelector;
	}

	public function getMonetaryUnit(): string {
		return $this->defaultCurrency->getCurrency()->getSymbol();
	}

	public function setPlayerLanguage(string $player, string $language): bool {
		$player = strtolower($player);
		$language = strtolower($language);
		if(isset($this->lang[$language])) {
			return $this->provider->setLanguage($player, $language);
		}
		return false;
	}

	/**
	 * Checks if currency is available to player if $player is instance of Player
	 *
	 * @param CommandSender|string $player
	 * @param Currency|string $currency
	 * @return bool
	 */
	public function setPlayerPreferredCurrency($player, $currency): bool {
		if($currency instanceof Currency) {
			$id = $this->getCurrencyId($currency);
			if($id === null) return false;

			$inst = $currency;
		}else{
			$id = $currency;
			$inst = $this->getCurrency($id);
			if($inst === null) return false;
		}
		$id = strtolower($id);

		if($player instanceof Player) {
			if(!$inst->isAvailableTo($player)) {
				return false;
			}
		}

		if($player instanceof CommandSender) {
			$player = $player->getName();
		}

		return $this->provider->setPreferredCurrency($player, $id);
	}

	/**
	 * @param CommandSender|string $player
	 * @param bool $allowNull Allows null on return value. If set false, it will return default currency by default.
	 * @return Currency|null
	 */
	public function getPlayerPreferredCurrency($player, bool $allowNull = true): ?Currency {
		if($player instanceof CommandSender) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		$id = $this->provider->getPreferredCurrency($player);
		$currency = $this->getCurrency($id);

		if($currency === null) {
			return $allowNull ? $currency : $this->defaultCurrency->getCurrency();
		}
		return $currency;
	}

	/**
	 * Checks if currency is registered to EconomyAPI by ID or Currency instance
	 * @param string|Currency $val
	 * @return bool
	 */
	public function hasCurrency($val): bool {
		if(is_string($val)) {
			return isset($this->currencies[$val]);
		}elseif($val instanceof Currency) {
			foreach($this->currencies as $id => $cur) {
				if($cur === $val) return true;
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function getAllMoney(): array {
		return $this->defaultCurrency->getBalanceRepository()->getAllBalances();
	}

	/**
	 * @deprecated This method is deprecated. Use hasAccount() instead.
	 * @param $player
	 * @param Currency|null $currency
	 * @return bool
	 */
	public function accountExists($player, ?Currency $currency = null): bool {
		return $this->hasAccount($player, $currency);
	}

	/**
	 * @param string|Player $player
	 * @param ?Currency $currency
	 *
	 * @return bool
	 */
	public function hasAccount($player, ?Currency $currency = null): bool {
		$holder = $this->findCurrencyHolder($currency, null);
		if($holder === null) return false;

		return $holder->getBalanceRepository()->hasAccount($player);
	}

	/**
	 * @param Player|string $player
	 * @param ?Currency $currency
	 *
	 * @return float|bool
	 */
	public function myMoney($player, ?Currency $currency = null) {
		$holder = $this->findCurrencyHolder($currency, $player);

		return $holder->getBalanceRepository()->getMoney($player);
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param ?Currency $currency
	 * @param ?Issuer $issuer
	 * @param bool $force
	 *
	 * @return int
	 */
	public function setMoney($player, float $amount, ?Currency $currency = null, ?Issuer $issuer = null, bool $force = false): int {
		$holder = $this->findCurrencyHolder($currency, $player);

		$ret = $this->canSetMoney($player, $amount, $force, $issuer, $holder->getCurrency());

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($ret === self::RET_VALID) {
			$revertAction = null;
			$result = $holder->getBalanceRepository()->setMoney($player, $amount, $revertAction);
			if($result < 0) return $result;

			(new MoneyChangedEvent($this, $player, $holder->getCurrency(), $result, $issuer))->call();
			return self::RET_SUCCESS;
		}

		return $ret;
	}

	private function canSetMoney($player, float $amount, bool $force, ?Issuer $issuer, Currency $currency): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}

		$holder = $this->validateCurrency($player, $currency);
		if($holder === null) return self::RET_INVALID_CURRENCY;

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($holder->getBalanceRepository()->hasAccount($player)) {
			$ev = new SetMoneyEvent($this, $player, $holder->getCurrency(), $amount, $issuer);
			$ev->call();
			if($ev->isCancelled() and $force === false) {
				return self::RET_CANCELLED;
			}

			return self::RET_VALID;
		}

		return self::RET_NO_ACCOUNT;
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param ?Currency $currency
	 * @param ?Issuer $issuer
	 * @param bool $force
	 *
	 * @return int
	 */
	public function addMoney($player, float $amount, ?Currency $currency = null, ?Issuer $issuer = null, bool $force = false): int {
		$holder = $this->findCurrencyHolder($currency, $player);

		$ret = $this->canAddMoney($player, $amount, $force, $issuer, $holder->getCurrency());

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($ret === self::RET_VALID) {
			$money = $holder->getBalanceRepository()->getMoney($player);

			$revertAction = null;
			$holder->getBalanceRepository()->addMoney($player, $amount, $revertAction);
			(new MoneyChangedEvent($this, $player, $holder->getCurrency(), $money, $issuer))->call();
			return self::RET_SUCCESS;
		}

		return $ret;
	}

	private function canAddMoney($player, float $amount, bool $force, ?Issuer $issuer, Currency $currency): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}

		$holder = $this->validateCurrency($player, $currency);
		if($holder === null) return self::RET_INVALID_CURRENCY;

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($holder->getBalanceRepository()->hasAccount($player)) {
			$ev = new AddMoneyEvent($this, $player, $holder->getCurrency(), $amount, $issuer);
			$ev->call();

			if($ev->setCancelled() and $force === false) {
				return self::RET_CANCELLED;
			}

			return self::RET_VALID;
		}else{
			return self::RET_NO_ACCOUNT;
		}
	}

	/**
	 * @param string|Player $player
	 * @param float $amount
	 * @param ?Currency $currency
	 * @param ?Issuer $issuer
	 * @param bool $force
	 *
	 * @return int
	 */
	public function reduceMoney($player, float $amount, ?Currency $currency = null, ?Issuer $issuer = null, bool $force = false): int {
		$holder = $this->findCurrencyHolder($currency, $player);

		$ret = $this->canReduceMoney($player, $amount, $force, $issuer, $holder->getCurrency());

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($ret === self::RET_VALID) {
			$money = $holder->getBalanceRepository()->getMoney($player);

			$revertActions = null;
			$holder->getBalanceRepository()->reduceMoney($player, $amount, $revertActions);
			(new MoneyChangedEvent($this, $player, $holder->getCurrency(), $money, $issuer))->call();
			return self::RET_SUCCESS;
		}

		return $ret;
	}

	private function canReduceMoney($player, float $amount, $force, ?Issuer $issuer, Currency $currency): int {
		if($amount < 0) {
			return self::RET_INVALID;
		}

		$holder = $this->validateCurrency($player, $currency);
		if($holder === null) return self::RET_INVALID_CURRENCY;

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($holder->getBalanceRepository()->hasAccount($player)) {
			$ev = new ReduceMoneyEvent($this, $player, $holder->getCurrency(), $amount, $issuer);
			$ev->call();
			if($ev->isCancelled() and $force === false) {
				return self::RET_CANCELLED;
			}

			return self::RET_VALID;
		}

		return self::RET_NO_ACCOUNT;
	}

	private function validateCurrency($player, Currency $currency) : ?CurrencyHolder {
		$holder = $this->getCurrencyHolder($currency);
		if($holder === null) {
			return null;
		}

		if($player instanceof Player) {
			if(!$holder->getCurrency()->isAvailableTo($player)) {
				return null;
			}
		}

		return $holder;
	}

	public function getDefaultCurrency(): Currency {
		return $this->defaultCurrency->getCurrency();
	}

	public function getDefaultCurrencyId(): string {
		return $this->defaultCurrency->getId();
	}

	/**
	 * @param string|Player $player
	 * @param string|?Currency $currency
	 * @param float|bool $defaultMoney
	 * @param ?Issuer $issuer
	 *
	 * @return bool
	 */
	public function createAccount($player, $currency = null, $defaultMoney = false, ?Issuer $issuer = null): bool {
		$holder = $this->findCurrencyHolder($currency, $player);

		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(!$holder->getBalanceRepository()->hasAccount($player)) {
			if($defaultMoney === false) {
				// if $defaultMoney is not set on parameter, look at user configured initial balance first
				// then fallback to currency specified amount if user did not define it
				$currencyConfig = $holder->getConfig();
				if($currencyConfig !== null) {
					$money = $currencyConfig->getDefaultMoney();
					if($money !== null) {
						$defaultMoney = $money;
					}
				}

				if($defaultMoney === false) {
					$defaultMoney = $holder->getCurrency()->getDefaultMoney();
				}
			}

			$ev = new CreateAccountEvent($this, $player, $holder->getCurrency(), $defaultMoney, $issuer);
			$ev->call();

			$holder->getBalanceRepository()->createAccount($player, $ev->getDefaultMoney());
			return true;
		}

		return false;
	}

	/**
	 * @experimental
	 *
	 * Executes multiple actions at the same time.
	 *
	 * Be aware that the function does not guarantee atomicity if actions in $transaction contains actions
	 * with multiple types of currencies. Also currency availability for a player is not considered during
	 * the transaction.
	 *
	 * @param Transaction $transaction
	 * @param Issuer|null $issuer
	 * @return bool Returns true if succeed or false if failed. If actions contain multiple currencies,
	 *              atomicity is not guaranteed.
	 */
	public function executeTransaction(Transaction $transaction, ?Issuer $issuer = null): bool {
		$transactionMap = [];
		foreach($transaction->getActions() as $action) {
			$key = $this->getCurrencyId($action->getCurrency());
			if($key === null)
				throw new InvalidArgumentException("Each action of transaction must reference registered Currency instance.");

			if(!isset($transactionMap[$key])) {
				$transactionMap[$key] = [];
			}

			$transactionMap[$key][] = $action;
		}

		$reverts = [];
		foreach($transactionMap as $currencyId => $actions) {
			$currency = $this->getCurrencyHolder($this->getCurrency($currencyId));
			if($currency === null) throw new AssertionError("This should not happen");

			$result = $currency->getBalanceRepository()->executeTransaction($actions);
			if($result->getState() === TransactionResult::FAILURE) {
				foreach($reverts as [$revertCurrency, $revertActions]) {
					/** @type CurrencyHolder $revertCurrency */
					$revertCurrency->getBalanceRepository()->revert($revertActions);
				}

				return $result->getReason();
			}

			$reverts[] = [$currency, $result->getReason()];
		}

		return true;
	}

	public function getSortByRange(Currency $currency, int $from, ?int $len = null): ?Promise {
		$holder = $this->getCurrencyHolder($currency);
		if($holder === null) return null;

		return $holder->getBalanceRepository()->sortByRange($from, $len);
	}

	public function getCurrencyConfig(Currency $currency): ?CurrencyConfig {
		foreach($this->currencies as $config) {
			if($config->getCurrency() === $currency) {
				return $config->getConfig();
			}
		}

		// 'null' is returned when given $currency is not registered to API
		// or registered too late
		return null;
	}

	private function getCurrencyHolder(Currency $currency): ?CurrencyHolder {
		foreach($this->currencies as $holder) {
			if($holder->getCurrency() === $currency) return $holder;
		}

		return null;
	}

	private function findCurrencyHolder(?Currency $currency, $player): CurrencyHolder {
		// $player argument is only used for finding default currency that player could use if $currency is null
		if($currency instanceof Currency) {
			return $this->getCurrencyHolder($currency);
		}else{
			if($player instanceof Player) {
				return $this->getCurrencyHolder($this->currencySelector->getDefaultCurrency($player));
			}
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

		if($this->currencySelector === null) {
			$this->currencySelector = new SimpleCurrencySelector($this);
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		if($this->pluginConfig->getSendCommandUsages()) {
			$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		}
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

		$this->registerDefaultCurrencies();

		$default = $this->getPluginConfig()->getDefaultCurrency();
		foreach($this->currencies as $key => $holder) {
			if($key === $default) {
				$this->defaultCurrency = $holder;

				$this->getLogger()->info('Default currency is set to: ' . $holder->getCurrency()->getName());
				break;
			}
		}

		$this->parseCurrencies();
		$this->initializeLanguage();
		$this->registerCommands();

		$this->provider->init();
	}

	private function checkUpdate(): bool {
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

	/**
	 * Register Currency to EconomyAPI.
	 * @param string $id
	 * @param Currency $currency
	 * @param Provider $provider
	 * @return bool True if there was no Currency that is already registered with the same ID, false otherwise.
	 */
	public function registerCurrency(string $id, Currency $currency, Provider $provider): bool {
		$id = strtolower($id);

		if(isset($this->currencies[$id])) {
			return false;
		}

		$this->currencies[$id] = new CurrencyHolder($this, $id, $currency, $provider);
		return true;
	}

	public function getCurrency(string $id): ?Currency {
		$id = strtolower($id);

		if(isset($this->currencies[$id])) {
			return $this->currencies[$id]->getCurrency();
		}

		return null;
	}

	/**
	 * Returns all currencies registered to EconomyAPI
	 * @return Currency[]
	 */
	public function getCurrencies(): array {
		$ret = [];

		foreach($this->currencies as $key => $holder) {
			$ret[$key] = $holder->getCurrency();
		}

		return $ret;
	}

	public function getCurrencyId(Currency $currency): ?string {
		foreach($this->currencies as $id => $holder) {
			if($holder->getCurrency() === $currency) return $id;
		}

		return null;
	}

	private function registerDefaultCurrencies() {
		$this->registerCurrency('dollar', new CurrencyDollar(), $this->parseProvider('Money.yml'));
		$this->registerCurrency('won', new CurrencyWon(), $this->parseProvider('Won.yml'));
	}

	private function parseProvider($file) {
		switch(strtolower($this->getPluginConfig()->getProvider())) {
			case 'yaml':
				return new YamlProvider($this, $file);
			/* case 'mysql':
				return new MySQLProvider($this); */
			default:
				return new DummyProvider();
		}
	}

	private function parseCurrencies() {
		$currencies = $this->pluginConfig->getCurrencies();
		foreach($currencies as $key => $data) {
			$key = strtolower($key);

			if(!isset($this->currencies[$key])) {
				continue;
			}

			$exchange = $data['exchange'] ?? [];
			foreach($exchange as $target => $value) {
				if(count($value) !== 2 or
					(!is_float($value[0]) and !is_int($value[0])) or
					(!is_float($value[1]) and !is_int($value[1]))) {
					$this->getLogger()->warning("Currency exchange rate for $key to $target is not valid. It will be excluded.");
					unset($exchange[$target]);
				}
			}

			$isExposed = $data['exposed'] ?? true;
			if(!is_bool($isExposed)) {
				$isExposed = true;
			}

			$holder = $this->currencies[$key];
			$holder->setConfig(
				new CurrencyConfig($holder->getCurrency(), $data['max'] ?? -1, $data['default'] ?? null, $exchange, $isExposed)
			);
		}
	}

	private function initializeLanguage() {
		foreach($this->getResources() as $resource) {
			if($resource->isFile() and substr(($filename = $resource->getFilename()), 0, 5) === "lang_") {
				$this->lang[substr($filename, 5, -5)] = json_decode(file_get_contents($resource->getPathname()), true);
			}
		}
		$this->lang[self::USER_DEFINED] = (new Config(
			$this->getDataFolder() . "messages.yml", Config::YAML, $this->lang[self::FALLBACK_LANGUAGE]
		))->getAll();
	}

	private function registerCommands() {
		$this->getServer()->getCommandMap()->registerAll("economyapi", [
			new GiveMoneyCommand($this),
			new MyMoneyCommand($this),
			new MyStatusCommand($this),
			new PayCommand($this),
			new SeeMoneyCommand($this),
			new SetMoneyCommand($this),
			new TakeMoneyCommand($this),
			new TopMoneyCommand($this),
			new EconomyCommand($this)
		]);
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();

		if(!$this->defaultCurrency->getBalanceRepository()->hasAccount($player)) {
			$this->getLogger()->debug("UserInfo of '" . $player->getName() . "' is not found. Creating account...");
			$this->createAccount($player, $this->defaultCurrency->getCurrency());
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

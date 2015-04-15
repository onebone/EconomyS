<?php

/**
 * @deprecated
 */

namespace onebone\economyapi\event\bank;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;

class MoneyChangedEvent extends EconomyAPIEvent{
	private $username, $money;
	public static $handlerList;

	public function __construct(EconomyAPI $plugin, $username, $money, $issuer){
		parent::__construct($plugin, $issuer);
		$this->username = $username;
		$this->money = $money;
	}

	/**
	 * @return string
	 */
	public function getUsername(){
		return $this->username;
	}

	/**
	 * @return float
	 */
	public function getMoney(){
		return $this->money;
	}
}
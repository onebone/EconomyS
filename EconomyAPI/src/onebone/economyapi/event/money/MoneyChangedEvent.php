<?php

namespace onebone\economyapi\event\money;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;

class MoneyChangedEvent extends EconomyAPIEvent{
	private $plugin, $username, $money;
	public static $handlerList;

	public function __construct(EconomyAPI $api, $username, $money, $issuer){
		$this->plugin = $api;
		$this->username = $username;
		$this->money = $money;
		$this->issuer = $issuer;
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
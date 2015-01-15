<?php

namespace onebone\economyapi\event\debt;


use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;

class DebtChangedEvent extends EconomyAPIEvent{
	private $plugin, $username, $debt;
	public static $handlerList;

	public function __construct(EconomyAPI $api, $username, $debt, $issuer){
		$this->plugin = $api;
		$this->username = $username;
		$this->debt = $debt;
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
	public function getDebt(){
		return $this->debt;
	}
}
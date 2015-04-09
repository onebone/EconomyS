<?php

namespace onebone\economyapi\event\debt;


use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\EconomyAPIEvent;

class DebtChangedEvent extends EconomyAPIEvent{
	private $username, $debt;
	public static $handlerList;

	public function __construct(EconomyAPI $plugin, $username, $debt, $issuer){
		parent::__construct($plugin, $issuer);
		$this->username = $username;
		$this->debt = $debt;
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
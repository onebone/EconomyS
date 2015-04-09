<?php

namespace onebone\economyapi\event\debt;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class AddDebtEvent extends EconomyAPIEvent{
	private $username, $amount;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $amount, $issuer){
		parent::__construct($plugin, $issuer);
		$this->username = $username;
		$this->amount = $amount;
	}
	
	public function getAmount(){
		return $this->amount;
	}
	
	public function getUsername(){
		return $this->username;
	}
}
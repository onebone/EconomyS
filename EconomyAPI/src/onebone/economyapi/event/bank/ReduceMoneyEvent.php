<?php

namespace onebone\economyapi\event\bank;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class ReduceMoneyEvent extends EconomyAPIEvent{
	private $username, $amount;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $amount, $issuer){
		parent::__construct($plugin, $issuer);
		$this->username = $username;
		$this->amount = $amount;
	}
	
	public function getUsername(){
		return $this->username;
	}
	
	public function getAmount(){
		return $this->amount;
	}
}
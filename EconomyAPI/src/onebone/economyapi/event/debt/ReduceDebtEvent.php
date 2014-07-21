<?php

namespace onebone\economyapi\event\debt;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class ReduceDebtEvent extends EconomyAPIEvent{
	private $plugin, $username, $amount, $issuer;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $amount, $issuer){
		$this->plugin = $plugin;
		$this->username = $username;
		$this->amount = $amount;
		$this->issuer = $issuer;
	}
	
	public function getUsername(){
		return $this->username;
	}
	
	public function getAmount(){
		return $this->amount;
	}
}
<?php

namespace onebone\economyapi\event\debt;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class AddDebtEvent extends EconomyAPIEvent{
	private $plugin, $username, $amount;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $amount, $issuer){
		$this->plugin = $plugin;
		$this->username = $username;
		$this->amount = $amount;
		$this->issuer = $issuer;
	}
	
	public function getAmount(){
		return $this->amount;
	}
	
	public function getUsername(){
		return $this->username;
	}
}
<?php

namespace onebone\economyapi\event\money;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class AddMoneyEvent extends EconomyAPIEvent{
	private $plugin, $username, $amount;
	public static $handlerList;
	
	public function __construct(EconomyAPI $api, $username, $amount, $issuer){
		$this->plugin = $api;
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
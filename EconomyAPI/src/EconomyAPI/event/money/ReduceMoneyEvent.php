<?php

namespace EconomyAPI\event\money;

use EconomyAPI\event\EconomyAPIEvent;

class ReduceMoneyEvent extends EconomyAPIEvent{
	private $plugin, $username, $amount, $issuer;
	public static $handlerList;
	
	public function __construct(\EconomyAPI\EconomyAPI $api, $username, $amount, $issuer){
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
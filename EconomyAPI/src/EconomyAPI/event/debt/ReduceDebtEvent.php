<?php

namespace EconomyAPI\event\debt;

use EconomyAPI\event\EconomyAPIEvent;
use EconomyAPI\EconomyAPI;

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
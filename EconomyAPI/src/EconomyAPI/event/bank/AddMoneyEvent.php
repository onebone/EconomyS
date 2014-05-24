<?php

namespace EconomyAPI\event\bank;

use EconomyAPI\event\EconomyAPIEvent;
use EconomyAPI\EconomyAPI;

class AddMoneyEvent extends EconomyAPIEvent{
	private $plugin, $username, $amount, $issuer;
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
<?php

namespace onebone\economyapi\event\money;

use onebone\economyapi\event\EconomyAPIEvent;

use onebone\economyapi\EconomyAPI;

class SetMoneyEvent extends EconomyAPIEvent{
	private $plugin, $username, $money, $issuer;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $money, $issuer){
		$this->plugin = $plugin;
		$this->username = $username;
		$this->money = $money;
		$this->issuer = $issuer;
	}
	
	public function getUsername(){
		return $this->username;
	}
	
	public function getMoney(){
		return $this->money;
	}
}
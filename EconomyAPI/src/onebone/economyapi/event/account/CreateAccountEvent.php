<?php

namespace onebone\economyapi\event\account;

use onebone\economyapi\event\EconomyAPIEvent;
use onebone\economyapi\EconomyAPI;

class CreateAccountEvent extends EconomyAPIEvent{
	private $player, $username, $defaultMoney, $defaultDebt, $defaultBankMoney;
	public static $handlerList;
	
	public function __construct(EconomyAPI $plugin, $username, $defaultMoney, $defaultDebt, $defaultBankMoney, $issuer){
		$this->plugin = $plugin;
		$this->username = $username;
		$this->defaultMoney = $defaultMoney;
		$this->defaultBankMoney = $defaultBankMoney;
		$this->issuer = $issuer;
	}
	
	public function getUsername(){
		return $this->username;
	}
	
	public function setDefaultMoney($money){
		$this->defaultMoney = $money;
	}
	
	public function setDefaultBankMoney($money){
		$this->defaultBankMoney = $money;
	}
	
	public function setDefaultDebt($money){
		$this->defaultDebt = $money;
	}
	
	public function getDefaultMoney(){
		return $this->defaultMoney;
	}
	
	public function getDefaultBankMoney(){
		return $this->defaultBankMoney;
	}
	
	public function getDefaultDebt(){
		return $this->defaultDebt;
	}
}
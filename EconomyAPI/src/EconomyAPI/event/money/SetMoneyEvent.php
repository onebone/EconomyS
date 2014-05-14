<?php

namespace EconomyAPI\event\money;

use EconomyAPI\event\EconomyAPIEvent;

class SetMoneyEvent extends EconomyAPIEvent{
	private $plugin, $username, $money, $issuer;
	public static $handlerList;
	
	public function __construct(\pocketmine\plugin\Plugin $plugin, $username, $money, $issuer){
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
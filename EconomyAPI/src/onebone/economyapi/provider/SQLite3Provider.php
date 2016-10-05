<?php
/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2016  onebone <jyc00410@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace onebone\economyapi\provider;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\task\MySQLPingTask;
use pocketmine\Player;

class SQLite3Provider implements Provider{
  	private $db;
	public function __construct($file){
		$this->db = new SQLite3($file);
		$this->db->exec("CREATE TABLE IF NOT EXISTS User_Money(id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, money FLOAT)");
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @return bool
	 */
	public function accountExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		$result = $this->db->query("SELECT * FROM user_money WHERE username='".$player."'");
		return $result->num_rows > 0 ? true:false;
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @param float $defaultMoney
	 * @return bool
	 */
	public function createAccount($player, $defaultMoney = 1000){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)){
			$this->db->query("INSERT INTO user_money (username, money) VALUES ('".$player."', {$defaultMoney})");
		}
		return false;
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @return bool
	 */
	public function removeAccount($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)) {
			$this->db->query("DELETE FROM user_money WHERE username='".$player."'");
		}
	}
  
	/**
	 * @param string $player
	 * @return float|bool
	 */
	public function getMoney($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)) {
			$this->db->query("SELECT money FROM user_money WHERE username='".$player."'");
		}
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function setMoney($player, $amount){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)) {
			$this->db->query("UPDATE user_money SET money={$amount} WHERE username='".$player."' ");
		}
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function addMoney($player, $amount){
		$amount = absolute($amount);
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)) {
			$m = $this->db->getMoney($player);
			$cash = $m+$amount;
			$this->db->query("UPDATE user_money SET money={$cash} where username='".$player."' ");
		}
	}
  
	/**
	 * @param \pocketmine\Player|string $player
	 * @param float $amount
	 * @return bool
	 */
	public function reduceMoney($player, $amount){
		$amount = absolute($amount);
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if(!$this->accountExists($player)) {
			$m = $this->db->getMoney($player);
			$cash = $m-$ammount;
			$this->db->query("UPDATE user_money SET money={$cash} where username='".$player."'");
		}
	}
  
	/**
	 * @return array
	 */
	public function getAll(){
		$this->db->query("SELECT * FROM user_money");
	}
  
	/**
	 * @return string
	 */
	public function getName(){
		return "SQLite3";
	}
  
	public function save(){
		//$this->db->save();
	}
  
	public function close(){
		$this->db->close();
	}
}

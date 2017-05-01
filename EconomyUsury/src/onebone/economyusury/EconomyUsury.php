<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
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

namespace onebone\economyusury;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;

use onebone\economyusury\commands\UsuryCommand;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\money\PayMoneyEvent;

class EconomyUsury extends PluginBase implements Listener{
	private $usuryHosts, $msg_queue, $schedule_req, $lang;
	
	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		if(!defined("\\onebone\\economyapi\\EconomyAPI::API_VERSION") or EconomyAPI::API_VERSION < 1){
			$this->getLogger()->warning("Your EconomyAPI version is not compatible with this plugin. Please update it.");
			return;
		}
		
		if(!is_file($this->getDataFolder()."usury.dat")){
			file_put_contents($this->getDataFolder()."usury.dat", serialize([]));
		}
		if(!is_file($this->getDataFolder()."msg_queue.dat")){
			file_put_contents($this->getDataFolder()."msg_queue.dat", serialize([]));
		}
		if(!is_file($this->getDataFolder()."schedule_required.dat")){
			file_put_contents($this->getDataFolder()."schedule_required.dat", serialize([]));
		}
		$this->saveResource("language.properties");
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES);
		
		$this->schedule_req = unserialize(file_get_contents($this->getDataFolder()."schedule_required.dat"));
		$this->msg_queue = unserialize(file_get_contents($this->getDataFolder()."msg_queue.dat"));
		$this->usuryHosts = unserialize(file_get_contents($this->getDataFolder()."usury.dat"));
		
		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->register("usury", new UsuryCommand("usury", $this));
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		foreach($this->usuryHosts as $host => $val){
			foreach($val["players"] as $player => $data){
				if($data[3] === null) continue;
				
				$tid = $this->getServer()->getScheduler()->scheduleDelayedTask(new DueTask($this, Item::get($data[0], $data[1], $data[2]), $player, $host), $data[4])->getTaskId();
				$tid2 = $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new InterestTask($this, $host, $player), $data[8], $val[1] * 1200)->getTaskId();
				$this->usuryHosts[$host]["players"][$player] = [$data[0], $data[1], $data[2], time(), $data[4], $data[5], $tid, time(), $data[8], $tid2];
			}
		}
	}
	
	public function getMessage($key, $val = ["%1", "%2", "%3", "%4"]){
		if($this->lang->exists($key)){
			if(count($val) < 3){
				$val[0] = isset($val[0]) ? $val[0]:"%1";
				$val[1] = isset($val[1]) ? $val[1]:"%2";
				$val[2] = isset($val[2]) ? $val[2]:"%3";
				$val[3] = isset($val[3]) ? $val[3]:"%4";
				$val[4] = isset($val[4]) ? $val[4]:"%5";
			}
			$val[5] = "\n";
			$val[6] = EconomyAPI::getInstance()->getMonetaryUnit();
			return str_replace(["%1", "%2", "%3", "%4", "%5", "\\n", "%MONETARY_UNIT%"], $val, $this->lang->get($key));
		}else{
			return $key;
		}
	}
	
	public function onDisable(){
		$this->validateDue();
		
		$saves = [
			"usury.dat" => $this->usuryHosts,
			"msg_queue.dat" => $this->msg_queue,
			"schedule_required.dat" => $this->schedule_req
		];
		foreach($saves as $fileName => $data){
			file_put_contents($this->getDataFolder().$fileName, serialize($data));
		}
	}
	
	public function validateDue($cancelTask = true){
		$now = time();
		foreach($this->usuryHosts as $host => $val){
			foreach($val["players"] as $player => $data){
				if($data[3] === null) continue;
				$reduce = (($now - $data[3]) * 20);
				$this->usuryHosts[$host]["players"][$player][3] = time();
				$this->usuryHosts[$host]["players"][$player][4] -= $reduce;
				if($cancelTask){
					if($this->getServer()->getScheduler()->isQueued($data[6])){
						$this->getServer()->getScheduler()->cancelTask($data[6]);
					}
				}
			}
		}
	}
	
	public function onJoinEvent(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		
		if(isset($this->msg_queue[$player->getName()])){
			foreach($this->msg_queue[$player->getName()] as $msg){
				$player->sendMessage($msg);
			}
			unset($this->msg_queue[$player->getName()]);
		}
		
		if(isset($this->schedule_req[$player->getName()])){
			foreach($this->schedule_req[$player->getName()] as $data){
				$tid = $this->getServer()->getScheduler()->scheduleDelayedTask(new DueTask($this, Item::get($data[0], $data[1], $data[2]), $player->getName(), $data[3]), $data[4])->getTaskId();
				$tid2 = $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new InterestTask($this, $data[3], $player->getName()), $this->usuryHosts[$data[3]][1] * 1200, $this->usuryHosts[$data[3]][1] * 1200)->getTaskId();
				$this->usuryHosts[$data[3]]["players"][$player->getName()] = [$data[0], $data[1], $data[2], time(), $data[4], $data[5], $tid, time(), $this->usuryHosts[$data[3]][1] * 1200, $tid2];
			}
			unset($this->schedule_req[$player->getName()]);
		}
	}
	
	public function onPayEvent(PayMoneyEvent $event){
		$target = strtolower($event->getTarget());
		$player = strtolower($event->getPayer());
		
		if(isset($this->usuryHosts[$target]["players"][$player])){
			$condition = $this->usuryHosts[$target]["players"][$player];
			
			$mustPay = $condition[5];
			$amount = $event->getAmount();
			
			if($mustPay <= $amount){
				$this->queueMessage($player, $this->getMessage("paid-all", [$target, "%2"]));
				$this->queueMessage($target, $this->getMessage("client-paid-all", [$player, "%2"]));
				
				$this->addItem($player, Item::get($condition[0], $condition[2], $condition[3]));
				
				$this->getServer()->getScheduler()->cancelTask($condition[6]);
				
				unset($this->usuryHosts[$target]["players"][$player]);
				return;
			}
			$this->usuryHosts[$target]["players"][$player][5] -= $amount;
			$this->queueMessage($player, $this->getMessage("loan-left", [$this->usuryHosts[$target]["players"][$player][5], $target]));
		}
	}
	
	public function usuryHostExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		return isset($this->usuryHosts[$player]) === true;
	}
	
	public function openUsuryHost($player, $interest, $interval){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(isset($this->usuryHosts[$player])){
			return false;
		}
		
		$this->usuryHosts[$player] = [
			$interest, $interval,
			"players" => []
		];
		return true;
	}
	
	public function closeUsuryHost($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(!isset($this->usuryHosts[$player])){
			return false;
		}
		
		foreach($this->usuryHosts[$player]["players"] as $username => $val){
			if(($p = $this->getServer()->getPlayerExact($username))){
				$p->getInventory()->addItem(Item::get($val[0], $val[1], $val[2]));
				continue;
			}
			$this->addItem($username, Item::get($val[0], $val[1], $val[2]));
			if($this->getServer()->getScheduler()->isQueued($val[6])){
				$this->getServer()->getScheduler()->cancelTask($val[6]);
			}
			if($this->getServer()->getScheduler()->isQueued($val[9])){
				$this->getServer()->getScheduler()->cancelTask($val[9]);
			}
		}
		
		$this->usuryHosts[$player] = null;
		unset($this->usuryHosts[$player]);
		return true;
	}
	
	public function isPlayerJoinedHost($player, $host){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		return isset($this->usuryHosts[$host]["players"][$player]) === true;
	}
	
	public function getAllHosts(){
		return $this->usuryHosts;
	}
	
	public function joinHost($player, $host, $due, Item $guarantee, $money){
		if($guarantee === null){
			throw new \Exception("Item cannot be null");
		}
		
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if(isset($this->usuryHosts[$host]["players"][$player])){
			return false;
		}
		if(!$this->containsItem($player, $guarantee)){
			return false;
		}
		$this->removeItem($player, $guarantee);
		
		if($this->getServer()->getPlayerExact($player) instanceof Player){
			$tid = $this->getServer()->getScheduler()->scheduleDelayedTask(new DueTask($this, $guarantee, $player, $host), $due * 1200)->getTaskId();
			$tid2 = $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new InterestTask($this, $host, $player), $this->usuryHosts[$host][1] * 1200, $this->usuryHosts[$host][1] * 1200)->getTaskId();
			$this->usuryHosts[$host]["players"][$player] = [
				$guarantee->getId(), $guarantee->getDamage(), $guarantee->getCount(), time(), $due * 1200, $money, $tid, time(),  $this->usuryHosts[$host][1] * 1200, $tid2
			];
			return true;
		}
		$this->schedule_req[$player][] = [$guarantee->getId(), $guarantee->getDamage(), $guarantee->getCount(), $host, $due * 1200, $money];
		return true;
	}
	
	public function getHostsJoined($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		$ret = [];
		foreach($this->usuryHosts as $host => $data){
			foreach($data["players"] as $p => $dummy){
				if($player === $p){
					$ret[] = $host;
					break;
				}
			}
		}
		return $ret;
	}
	
	public function getJoinedPlayers($host){
		$host = strtolower($host);
		if(!isset($this->usuryHosts[$host])){
			return false;
		}
		return $this->usuryHosts[$host]["players"];
	}
	
	public function removePlayerFromHost($player, $host){
		if(!isset($this->usuryHosts[$host]["players"][$player])){
			return false;
		}
		if($this->getServer()->getScheduler()->isQueued($this->usuryHosts[$host]["players"][$player][5])){
			$this->getServer()->getScheduler()->cancelTask($this->usuryHosts[$host]["players"][$player][5]);
		}
		if($this->getServer()->getScheduler()->isQueued($this->usuryHosts[$host]["players"][$player][9])){
			$this->getServer()->getScheduler()->cancelTask($this->usuryHosts[$host]["players"][$player][9]);
		}
		unset($this->usuryHosts[$host]["players"][$player]);
		return true;
	}
	
	public function queueMessage($player, $message, $checkPlayer = true){
		if($checkPlayer === true and ($p = $this->getServer()->getPlayerExact($player)) instanceof Player){
			$p->sendMessage($message);
			return false;
		}
		$this->msg_queue[$player][] = $message;
		return true;
	}
	
	public function containsItem($player, Item $i){
		if(($p = $this->getServer()->getPlayerExact($player)) instanceof Player){
			return $p->getInventory()->contains($i);
		}
		
		$data = $this->getServer()->getOfflinePlayerData($player);
		$count = 0;
		foreach($data->Inventory as $key => $item){
			if($key > 8){
				if($item["id"] == $i->getId() and $item["Damage"] == $i->getDamage()){
					$count += $item["Count"];
					if($count >= $i->getCount()) return true;
				}
			}
		}
		return false;
	}
	
	public function addItem($player, Item $i){
		if(($p = $this->getServer()->getPlayerExact($player)) instanceof Player){
			$p->getInventory()->addItem($i);
		}
		
		$data = $this->getServer()->getOfflinePlayerData($player);
		$count = $i->getCount();
		foreach($data->Inventory as $key => $item){
			if($key > 8){
				if($item["id"] == $i->getId() and $item["Damage"] == $i->getDamage()){
					$giveCnt = min($i->getMaxStackSize() - $item["Count"], $count);
					$count -= $giveCnt;
					
					$item["Count"] += $giveCnt;
					if($count <= 0) goto save;
				}
			}
		}
		foreach($data->Inventory as $key => $item){
			if($key > 8){
				if($item["id"] == 0){
					$giveCnt = min($i->getMaxStackSize(), $count);
					$count -= $giveCnt;
					
					$item["id"] = $i->getId();
					$item["Damage"] = $i->getDamage();
					$item["Count"] = $giveCnt;
					if($count <= 0) break;
				}
			}
		}
		save:
		$this->getServer()->saveOfflinePlayerData($player, $data);
	}
	
	public function removeItem($player, Item $i){
		if(($p = $this->getServer()->getPlayerExact($player)) instanceof Player){
			$p->getInventory()->removeItem($i);
			return;
		}
		$data = $this->getServer()->getOfflinePlayerData($player);
		$count = $i->getCount();
		foreach($data->Inventory as $key => $item){
			if($key > 8){
				if($item["id"] == $i->getId() and $item["Damage"] == $i->getDamage()){
					$removeCnt = min($count, $item["Count"]);
					$count -= $removeCnt;
					
					$item["Count"] -= $removeCnt;
					if($item["Count"] <= 0){
						$item["id"] = 0;
						$item["Damage"] = 0;
					}
					if($count <= 0){
						break;
					}
				}
			}
		}
		$this->getServer()->saveOfflinePlayerData($player, $data);
	}
	
	public function handleInterest($host, $player){
		$money = $this->usuryHosts[$host]["players"][$player][5];
		$this->usuryHosts[$host]["players"][$player][5] += round(($money * ($this->usuryHosts[$host][0] / 100)), 2);
		
		$this->usuryHosts[$host]["players"][$player][7] = time();
	}
}

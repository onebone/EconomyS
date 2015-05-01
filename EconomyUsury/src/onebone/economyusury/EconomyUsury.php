<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2015  onebone <jyc00410@gmail.com>
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
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;

use onebone\economyusury\commands\UsuryCommand;

class EconomyUsury extends PluginBase implements Listener{
	private $usuryHosts;
	
	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		
		if(!is_file($this->getDataFolder()."usury.dat")){
			file_put_contents($this->getDataFolder()."usury.dat", serialize([]));
		}
		$this->usuryHosts = unserialize(file_get_contents($this->getDataFolder()."usury.dat"));
		
		$commandMap = $this->getServer()->getCommandMap();
		$commandMap->register("usury", new UsuryCommand("usury", $this));
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onLoginEvent(PlayerLoginEvent $event){
		
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
		
		foreach($this->usuryHosts[$player]["players"] as $key => $username){ // TODO: Debug here
			if(($player = $this->getServer()->getPlayerExact($username))){
				$player->getInventory()->addItem(Item::get($this->usuryHosts[$player]["players"][$key][0], $this->usuryHosts[$player]["players"][$key][1], $this->usuryHosts[$player]["players"][$key][2]));
				continue;
			}
			$data = $this->getServer()->getOfflinePlayerData($username);
			$count = $this->usuryHosts[$player]["players"][$key][2];
			foreach($data->Inventory as $key => $item){ //FIXME: The $key is already used in outer foreach loop
				if($item["id"] == $this->usuryHosts[$player]["players"][$key][0] and $item["Damage"] == $this->usuryHosts[$player]["players"][$key][1]){
					$i = Item::get($this->usuryHosts[$player]["players"][$key][0], $this->usuryHosts[$player]["players"][$key][1], $this->usuryHosts[$player]["players"][$key][2]);
					$giveCnt = min($count, $i->getMaxStackSize());
					$count -= $giveCnt;
					
					$item["Count"] += $giveCnt;
					
					if($count <= 0){
						break;
					}
				}
			}
			$this->getServer()->saveOfflinePlayerData($username, $data);
		}
		
		unset($this->usuryHosts[$player]);
		return true;
	}
}
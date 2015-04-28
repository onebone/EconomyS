<?php

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
			foreach($data->Inventory as $key => $item){
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
	}
}
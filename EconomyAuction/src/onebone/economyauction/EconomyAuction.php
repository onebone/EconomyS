<?php

namespace onebone\economyauction;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
use pocketmine\item\Item;

use onebone\economyapi\EconomyAPI;

class EconomyAuction extends PluginBase{
	/*
	@var int[] $auctions
	key : player name
	[0] : item
	[1] : meta
	[2] : count
	[3] : start price
	[4] : buying player
	[5] : buying price
	[6] : remain time
	[7] : start time
	[8] : schedule id
	*/
	private $auctions, $queue;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(!is_file($this->getDataFolder()."Auctions.dat")){
			file_put_contents($this->getDataFolder()."Auctions.dat", serialize(array()));
		}
		if(!is_file($this->getDataFolder()."QuitQueue.dat")){
			file_put_contents($this->getDataFolder()."QuitQueue.dat", serialize(array()));
		}
		$this->auctions = unserialize(file_get_contents($this->getDataFolder()."Auctions.dat"));
		$this->queue = unserialize(file_get_contents($this->getDataFolder()."QuitQueue.dat"));
		
		foreach($this->auctions as $player => $data){
			if(isset($this->auctions[$player][6])){
				$id = $this->getServer()->getScheduler()->scheduleDelayedTask($this->auctions[$player][6], new CallbackTask(array($this, "quitAuction"), $player))->getTaskId();
				$this->auctions[$player][7] = time();
				$this->auctions[$player][8] = $id;
			}
		}
	}
	
	public function onDisable(){
		$now = time();
		foreach($this->auctions as $player => $data){
			if(isset($this->auctions[$player][6])){
				$this->auctions[$player][6] -= ($now - $this->auctions[$player][7]);
			}
		}
		file_put_contents($this->getDataFolder()."Auctions.dat", serialize($this->auctions));
		file_put_contents($this->getDataFolder()."QuitQueue.dat", serialize($this->queue));
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "auction":
			$sub = array_shift($params);
			switch($sub){
				case "start":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				if(isset($this->auctions[$sender->getName()])){
					$sender->sendMessage("You already have ongoing auction");
					break;
				}
				$item = array_shift($params);
				$count = (int)array_shift($params);
				$startPrice = array_shift($params);
				if(trim($item) === "" or trim($count) === "" or trim($startPrice) === ""){
					$sender->sendMessage("Usage: /auction start <item> <count> <start price>");
					break;
				}
				$e = explode(":", $item);
				if(!is_numeric($e[0])){
					$e[0] = 1;
				}
				if(!isset($e[1]) or !is_numeric($e[1])){
					$e[1] = 0;
				}
				$e = array(
					(int)$e[0],
					(int)$e[1]
				);
				$cnt = 0;
				foreach($sender->getInventory()->getContents() as $i){
					if($i->getID() == $e[0] and $i->getDamage() == $e[1]){
						++$cnt;
					}
					if($count <= $cnt){
						break;
					}
				}
				if($count <= $cnt){
					$sender->getInventory()->removeItem((new Item($e[0], $e[1], $count)));
					$this->auctions[strtolower($sender->getName())] = array(
						$e[0], $e[1], (int) $count, (int) $startPrice, null, (int) $startPrice, null, null
					);
					$this->getServer()->broadcastMessage($sender->getName()."'s auction has just started.");
				}else{
					$sender->sendMessage("You don't have enough items");
				}
				break;
				case "stop":
				$auction = array_shift($params);
				if(trim($auction) === "" and !$sender instanceof Player){
					$sender->sendMessage("Usage: /auction stop <player>");
					break;
				}elseif(trim($auction) === "" and $sender instanceof Player){
					$auction = $sender->getName();
				}else{
					$player = $this->getServer()->getPlayer($auction);
					if($player instanceof Player){
						$auction = $player->getName();
					}
				}
				$auction = strtolower($auction);
				if(!isset($this->auctions[$auction])){
					$sender->sendMessage((strtolower($sender->getName()) === $auction ? "You have":"$auction has")." no ongoing auction");
					break;
				}
				$this->quitAuction($auction);
				$sender->sendMessage((strtolower($sender->getName()) === $auction ? "Your":"$auction's")." auction has successfully stopped.");
				break;
				case "time":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					return true;
				}
				$item = array_shift($params);
				$count = array_shift($params);
				$startPrice = array_shift($params);
				$time = array_shift($params);
				if(trim($item) === "" or !is_numeric($count) or !is_numeric($startPrice) or !is_numeric($time)){
					$sender->sendMessage("Usage: /auction time <item> <count> <start price> <time>");
					break;
				}
				$e = explode(":", $item);
				if(!is_numeric($e[0])){
					$e[0] = 1;
				}
				if(!isset($e[1]) or !is_numeric($e[1])){
					$e[1] = 0;
				}
				$e = array(
					(int)$e[0],
					(int)$e[1]
				);
				$cnt = 0;
				foreach($sender->getInventory()->getContents() as $i){
					if($i->getID() === $e[0] and $i->getDamage() === $e[1]){
						++$cnt;
					}
					if($count <= $cnt){
						break;
					}
				}
				
				if($count <= $cnt){
					$sender->getInventory()->removeItem(new Item($e[0], $e[1], $count));
					$id = $this->getServer()->getScheduler()->scheduleDelayedTask(($time * 20), new CallbackTask(array($this, "quitAuction"), $sender->getName()))->getTaskId();
					$this->auctions[strtolower($sender->getName())] = array(
						$e[0], $e[1], (int) $count, (int) $startPrice, null, (int) $startPrice, $time, time(), $id
					);
					$this->getServer()->broadcastMessage($sender->getName()."'s auction has just started.");
				}else{
					$sender->sendMessage("You don't have enough items");
				}
				break;
				case "bid":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
					break;
				}
				$player = array_shift($params);
				$price = array_shift($params);
				if(trim($player) === "" or trim($price) === ""){
					$sender->sendMessage("Usage: /auction bid <player> <price>");
					break;
				}
				if(!isset($this->auctions[$player])){
					$sender->sendMessage("Auction by \"$player\" does not exist");
					break;
				}
				if($price > (int)$this->auctions[$player][5]){
					$this->auctions[$player][5] = $price;
					$this->auctions[$player][4] = $sender->getName();
					$sender->sendMessage("You have bid $$price to auction by \"$player\"");
				}else{
					$sender->sendMessage("Current price is bigger than you have tried to bid");
				}
				break;
				case "list":
				$output = "Auctions list:\n";
				foreach($this->auctions as $player => $data){
					$price = $data[5] === null ? $data[3] : $data[5];
					$p = $data[4] === null ? "No player":$data[4];
					$output .= "##".$player." | $$price | $p\n";
				}
				$output = substr($output, 0, -1);
				$sender->sendMessage($output);
				break;
				default:
				$sender->sendMessage("Usage: ".$command->getUsage());
			}
			return true;
		}
	}
	
	public function quitAuction($auction){
		if($this->auctions[$auction][7] !== null){
			$this->getServer()->getScheduler()->cancelTask($this->auctions[$auction][7]);
		}
		if($this->auctions[$auction][4] !== null){
			$p = $this->getServer()->getPlayerExact($this->auctions[$auction][4]);
			if($p instanceof Player){
				$p->getInventory()->addItem(new Item($this->auctions[$auction][0], $this->auctions[$auction][1], $this->auctions[$auction][2]));
				EconomyAPI::getInstance()->reduceMoney($p, $this->auctions[$auction][5], true, "EconomyAuction");
				$p->sendMessage("You've got item from the auction");
			}else{
				$this->queue[$this->auctions[$auction][4]] = array(
					$this->auctions[$auction][0], $this->auctions[$auction][1], $this->auctions[$auction][2]
				);
			}
			EconomyAPI::getInstance()->addMoney($auction, $this->auctions[$auction][5], true, "EconomyAuction");
		}else{
			$p = $this->getServer()->getPlayerExact($auction);
			if($p instanceof Player){
				$p->getInventory()->addItem(new Item($this->auctions[$auction][0], $this->auctions[$auction][1], $this->auctions[$auction][2]));
			}else{
				$this->queue[$auction] = array(
					$this->auctions[$auction][0], $this->auctions[$auction][1], $this->auctions[$auction][2]
				);
			}
		}
		if(isset($this->auctions[$auction][7])){
			$this->getServer()->getScheduler()->cancelTask($this->auctions[$auction][7]);
		}
		unset($this->auctions[$auction]);
	}
}
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

namespace onebone\economyauction;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

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
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		$this->saveDefaultConfig();
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
				$id = $this->getServer()->getScheduler()->scheduleDelayedTask(new QuitAuctionTask($this, $player), $this->auctions[$player][6])->getTaskId();
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
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $params) : bool{
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
				$tax = $this->getConfig()->get("auction-tax");
				if($tax > EconomyAPI::getInstance()->myMoney($sender)){
					$sender->sendMessage("You don't have enough money to start auction. Auction tax : ".$tax);
					break;
				}
				
				$item = array_shift($params);
				$count = array_shift($params);
				$startPrice = array_shift($params);
				if(trim($item) === "" or !is_numeric($count) or !is_numeric($count)){
					$sender->sendMessage("Usage: /auction start <item> <count> <start price>");
					break;
				}

				$count = (int) $count;
				$item = Item::fromString($item);

				$cnt = 0;
				foreach($sender->getInventory()->getContents() as $i){
					if($i->equals($item)){
						$cnt += $i->getCount();
						if($count <= $cnt){
							break;
						}
					}
				}
				if($count <= $cnt){
					$item->setCount($count);
					$sender->getInventory()->removeItem($item);

					$this->auctions[strtolower($sender->getName())] = array(
						$item->getID(), $item->getDamage(), $count, (float) $startPrice, null, (float) $startPrice, null, null
					);
					$this->getServer()->broadcastMessage(TextFormat::GREEN.$sender->getName().TextFormat::RESET."'s auction has just started.");
					EconomyAPI::getInstance()->reduceMoney($sender, $tax);
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
				$item = Item::fromString($item);
				$count = (int) $count;

				$cnt = 0;
				foreach($sender->getInventory()->getContents() as $i){
					if($i->equals($item)){
						$cnt += $i->getCount();
					}
					if($count <= $cnt){
						break;
					}
				}
				
				if($count <= $cnt){
					$item->setCount($count);
					$sender->getInventory()->removeItem($item);
					$id = $this->getServer()->getScheduler()->scheduleDelayedTask(new QuitAuctionTask($this, $sender->getName()), ($time * 20))->getTaskId();
					$this->auctions[strtolower($sender->getName())] = array(
						$item->getID(), $item->getDamage(), $count, (float) $startPrice, null, (float) $startPrice, $time, time(), $id
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
				if(trim($player) === "" or !is_numeric($price)){
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
					$sender->sendMessage("You have bid ".EconomyAPI::getInstance()->getMonetaryUnit()."$price to auction by \"$player\"");
				}else{
					$sender->sendMessage("Current price is bigger than you have tried to bid");
				}
				break;
				case "list":
				$output = "Auctions list:\n";
				foreach($this->auctions as $player => $data){
					$price = $data[5] === null ? $data[3] : $data[5];
					$p = $data[4] === null ? "No player":$data[4];
					$output .= "##".$player." | ".EconomyAPI::getInstance()->getMonetaryUnit()."$price | ".$data[2]." of ".$data[0].":".$data[1]." | $p\n";
				}
				$output = substr($output, 0, -1);
				$sender->sendMessage($output);
				break;
				default:
				$sender->sendMessage("Usage: ".$command->getUsage());
			}
			return true;

			default:
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
				$p->sendMessage("Your auction was finished without buyer.");
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

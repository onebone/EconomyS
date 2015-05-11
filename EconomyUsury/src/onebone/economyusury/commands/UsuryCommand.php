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

namespace onebone\economyusury\commands;

use pocketmine\command\PluginCommand;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class UsuryCommand extends PluginCommand implements PluginIdentifiableCommand, Listener{
	private $requests = [];
	
	public function __construct($cmd = "usury", $plugin){
		parent::__construct($cmd, $plugin);
		$this->setUsage("/$cmd <host|request|cancel>");
		$this->setDescription("Usury master command");
		
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}
	
	public function onPlayerJoin(PlayerJoinEvent $event){
		if(isset($this->requests[strtolower($event->getPlayer()->getName())])){
			$event->getPlayer()->sendMessage("You have received ".TextFormat::GREEN.count($this->requests[strtolower($event->getPlayer()->getName())]).TextFormat::RESET." usury request(s).");
		}
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		switch(array_shift($params)){
			case "host":
			switch(array_shift($params)){
				case "open":
				if($this->getPlugin()->usuryHostExists($sender->getName())){
					$sender->sendMessage("You already have usury host.");
					break;
				}
				
				$interest = array_shift($params);
				$interval = array_shift($params);
				
				if(trim($interest) == "" or trim($interval) == ""){
					$sender->sendMessage("Usage: /usury host open <interest> <interval>");
					break;
				}
				
				$this->getPlugin()->openUsuryHost($sender->getName(), $interest, $interval);
				$sender->sendMessage("Your usury host was opened.");
				break;
				case "close":
				$success = $this->getPlugin()->closeUsuryHost($sender->getName());
				if($success){
					$sender->sendMessage("You have closed your usury host. All the queues were cancelled and guarantee items were returned.");
				}else{
					$sender->sendMessage("You don't have usury host opened.");
				}
				break;
				case "accept":
				$player = strtolower(array_shift($params));
				if(trim($player) == ""){
					$sender->sendMessage("Usage: /usury host accept <player>");
					break;
				}
				if(isset($this->requests[strtolower($sender->getName())][$player])){
					$this->getPlugin()->joinHost($player, $sender->getName(), $this->requests[strtolower($sender->getName())][$player][1], $this->requests[strtolower($sender->getName())][$player][0], $this->requests[strtolower($sender->getName())][$player][2]);
					$sender->sendMessage("You have accepted player ".TextFormat::GREEN.$player.TextFormat::RESET." to your usury host.");
					$this->getPlugin()->queueMessage($player, "You are accepted to the usury host by ".TextFormat::GREEN.$sender->getName());
					EconomyAPI::getInstance()->addMoney($player, $this->requests[strtolower($sender->getName())][$player][2], true, "EconomyUsury");
					EconomyAPI::getInstance()->reduceMoney($sender->getName(), $this->requests[strtolower($sender->getName())][$player][2], true, "EconomyUsury");
					unset($this->requests[strtolower($sender->getName())][$player]);
					return true;
				}
				$sender->sendMessage("You don't have player ".TextFormat::GREEN.$player.TextFormat::RESET." in your request list.");
				break;
				case "decline":
				$player = strtolower(array_shift($params));
				if(trim($player) === ""){
					$sender->sendMessage("Usage: /usury host decline <player>");
					break;
				}
				if(isset($this->requests[strtolower($sender->getName())][$player])){
					unset($this->requests[strtolower($sender->getName())][$player]);
					$this->getPlugin()->queueMessage($player, "Your usury request was declined by ".TextFormat::GREEN.$sender->getName());
					$sender->sendMessage("You have declined request from ".TextFormat::GREEN.$player);
				}else{
					$sender->sendMessage("You don't have request from ".TextFormat::GREEN.$player);
				}
				break;
				case "list":
				if(!isset($this->requests[strtolower($sender->getName())]) or count($this->requests[strtolower($sender->getName())]) <= 0){
					$sender->sendMessage("You don't have any request received.");
					return true;
				}
				$msg = TextFormat::GREEN.count($this->requests[strtolower($sender->getName())]).TextFormat::RESET." players requested to your usury host: \n";
				foreach($this->requests[strtolower($sender->getName())] as $player => $condition){
					$msg .= TextFormat::GREEN.$player.TextFormat::RESET.": Item (".$condition[0]->getCount()." of ".$condition[0]->getName()."), due (".TextFormat::AQUA.$condition[1].TextFormat::RESET." min(s), money (".TextFormat::GOLD.EconomyAPI::getInstance()->getMonetaryUnit().$condition[2].TextFormat::RESET."))\n";
				}
				$sender->sendMessage($msg);
				break;
				default:
				$sender->sendMessage("Usage: /usury host <open|close|accept|decline|list>");
			}
			break;
			case "request":
			$requestTo = strtolower(array_shift($params));
			$item = array_shift($params);
			$count = array_shift($params);
			$due = array_shift($params);
			$money = array_shift($params);
			if(trim($requestTo) == "" or trim($item) == "" or !is_numeric($count) or !is_numeric($due) or !is_numeric($money)){
				$sender->sendMessage("Usage: /usury request <host> <guarantee item> <count> <due> <money>");
				break;
			}
			
			if(!$this->getPlugin()->usuryHostExists($requestTo)){
				$sender->sendMessage("There's no usury host ".TextFormat::GREEN.$requestTo.TextFormat::RESET);
				break;
			}
			
			if($requestTo === strtolower($sender->getName())){
				$sender->sendMessage("You cannot join your own host.");
				break;
			}
			
			if(isset($this->requests[$requestTo][strtolower($sender->getName())]) or $this->getPlugin()->isPlayerJoinedHost($sender->getName(), $requestTo)){
				$sender->sendMessage("You are already related to the host ".TextFormat::GREEN.$requestTo.TextFormat::RESET);
				break;
			}
			
			$item = Item::fromString($item);
			$item->setCount($count);
			if($sender->getInventory()->contains($item)){
				$this->requests[$requestTo][strtolower($sender->getName())] = [$item, $due, $money];
				$sender->sendMessage("You have sent request to host ".TextFormat::GREEN.$requestTo.TextFormat::RESET);
				if(($player = $this->getPlugin()->getServer()->getPlayerExact($requestTo)) instanceof Player){
					$player->sendMessage("You received a usury host client request by ".TextFormat::GREEN.$sender->getName().TextFormat::RESET);
				}
			}else{
				$sender->sendMessage(TextFormat::RED."You don't have enough guarantee items!");
			}
			break;
			case "cancel":
			$host = strtolower(array_shift($params));
			if(trim($host) === ""){
				$sender->sendMessage("Usage: /usury cancel <host>");
				break;
			}
			if(isset($this->requests[$host][strtolower($sender->getName())])){
				unset($this->requests[$host][strtolower($sender->getName())]);
				$sender->sendMessage("Usury request to ".TextFormat::GREEN.$host.TextFormat::RESET." was cancelled.");
			}else{
				$sender->sendMessage("You have no request sent to ".TextFormat::GREEN.$host);
			}
			break;
			case "list":
			$msg = "There are ".TextFormat::GREEN.count($this->getPlugin()->getAllHosts()).TextFormat::RESET." hosts running: \n";
			foreach($this->getPlugin()->getAllHosts() as $host => $data){
				$ic = TextFormat::GREEN;
				if($data[0] >= 50){
					$ic = TextFormat::YELLOW;
				}elseif($data[0] >= 100){
					$ic = TextFormat::RED;
				}
				$msg .= TextFormat::GREEN.$host.TextFormat::RESET.": ".TextFormat::AQUA.count($data["players"]).TextFormat::RESET." client(s), interest : ".$ic.$data[0]."%".TextFormat::RESET." per ".TextFormat::GOLD.$data[1].TextFormat::RESET."min(s)\n";
			}
			$sender->sendMessage($msg);
			break;
			default:
			$sender->sendMessage("Usage: ".$this->getUsage());
		}
		return true;
	}
}
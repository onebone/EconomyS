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

use pocketmine\scheduler\PluginTask;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

class DueTask extends PluginTask{
	private $guarantee, $playerName, $hostOwner;
	
	public function __construct(EconomyUsury $plugin, Item $guarantee, $playerName, $hostOwner){
		parent::__construct($plugin);
		
		$this->guarantee = $guarantee;
		$this->playerName = $playerName;
		$this->hostOwner = $hostOwner;
	}
	
	public function onRun($currentTick){
		$this->removeItem();
	}
	
	public function removeItem(){
		/** @var $owner EconomyUsury */
		$owner = $this->getOwner();

		if(($player = $owner->getServer()->getPlayerExact($this->playerName)) instanceof Player){
			$player->sendMessage("Your usury with ".TextFormat::GREEN.$this->hostOwner.TextFormat::RESET." due was expired. Your guarantee item was paid to the host.");
		}else{
			$owner->queueMessage($this->playerName, "Your usury with ".TextFormat::GREEN.$this->hostOwner.TextFormat::RESET." due was expired. Your guarantee item was paid to the host.");
		}
		
		if(($player = $owner->getServer()->getPlayerExact($this->hostOwner)) instanceof Player){
			$player->getInventory()->addItem($this->guarantee);
			$player->sendMessage("Usury of ".TextFormat::GREEN.$this->playerName.TextFormat::RESET." was expired. Guarantee item was paid to your inventory.");
		}else{
			$data = $owner->getServer()->getOfflinePlayerData($this->hostOwner);
			$c = $this->guarantee->getCount();
			$owner->addItem($this->hostOwner, $this->guarantee);
		}
		$owner->removePlayerFromHost($this->playerName, $this->hostOwner);
	}
}
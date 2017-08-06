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
	
	public function onRun(int $currentTick){
		$this->removeItem();
	}
	
	public function removeItem(){
		/** @var $owner EconomyUsury */
		$owner = $this->getOwner();

		if(($player = $owner->getServer()->getPlayerExact($this->playerName)) instanceof Player){
			$player->sendMessage($owner->getMessage("client-usury-expired", [$this->hostOwner]));
		}else{
			$owner->queueMessage($this->playerName, $owner->getMessage("client-usury-expired", [$this->hostOwner]));
		}
		
		if(($player = $owner->getServer()->getPlayerExact($this->hostOwner)) instanceof Player){
			$player->getInventory()->addItem($this->guarantee);
			$player->sendMessage($owner->getMessage("usury-expired", [$this->playerName]));
		}else{
			$data = $owner->getServer()->getOfflinePlayerData($this->hostOwner);
			$c = $this->guarantee->getCount();
			$owner->addItem($this->hostOwner, $this->guarantee);
			$owner->queueMessage($this->hostOwner, $owner->getMessage("usury-expired", [$this->playerName], false));
		}
		$owner->removePlayerFromHost($this->playerName, $this->hostOwner);
	}
}

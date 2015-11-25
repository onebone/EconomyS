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

namespace onebone\economysell;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\Config;
use pocketmine\item\Item;

use onebone\economyapi\EconomyAPI;

#define TAG 1

class EconomySell extends PluginBase implements Listener {
	private $sell;
	private $placeQueue;

	/**
	 *
	 * @var Config
	 */
	private $sellSign, $lang;

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->saveDefaultConfig();

		$this->sell = (new Config($this->getDataFolder()."Sell.yml", Config::YAML))->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->prepareLangPref();
		$this->placeQueue = [];
	}

	public function onDisable(){
		$cfg = new Config($this->getDataFolder()."Sell.yml", Config::YAML);
		$cfg->setAll($this->sell);
		$cfg->save();
	}

	private function prepareLangPref(){
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, array(
				"wrong-format" => "Please write your sign with right format",
				"item-not-support" => "Item %1 is not supported on EconomySell",
				"no-permission-create" => "You don't have permission to create sell center",
				"sell-created" => "Sell center has been created (%1 = %MONETARY_UNIT%%2)",
				"removed-sell" => "Sell center has been removed",
				"creative-mode" => "You are in creative mode",
				"no-permission-sell" => "You don't have permission to sell item",
				"no-permission-break" => "You don't have permission to break sell center",
				"tap-again" => "Are you sure to sell %1 (%MONETARY_UNIT%%2)? Tap again to confirm",
				"no-item" => "You have no item to sell",
				"sold-item" => "You have sold %1 of %2 for %MONETARY_UNIT%%3"
		));

		$this->sellSign = new Config($this->getDataFolder()."SellSign.yml", Config::YAML, array(
				"sell" => array(
						"§1[SELL]",
						"%MONETARY_UNIT%%1",
						"%2",
						"Amount : §l%3"
				)
		));
	}

	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%MONETARY_UNIT%", "%1","%2", "%3"), array(EconomyAPI::getInstance()->getMonetaryUnit(), $val[0], $val[1], $val[2]),$this->lang->get($key));
		}
		return "There's no message named \"$key\"";
	}

	public function onSignChange(SignChangeEvent $event){
		$tag = $event->getLine(0);
		if(($val = $this->checkTag($tag)) !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission ("economysell.sell.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			if(!is_numeric($event->getLine(1)) or !is_numeric($event->getLine(3))){
				$player->sendMessage($this->getMessage("wrong-format"));
				return;
			}
			$item = Item::fromString($event->getLine(2));
			if($item === false){
				$player->sendMessage($this->getMessage("item-not-support", array($event->getLine (2),"", "" )));
				return;
			}
			
			$block = $event->getBlock();
			$this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$player->getLevel()->getName()] = array(
					"x" => $block->getX(),
					"y" => $block->getY(),
					"z" => $block->getZ(),
					"level" => $player->getLevel()->getName(),
					"cost" => (int) $event->getLine(1),
					"item" =>  (int) $item->getID(),
					"itemName" => $item->getName(),
					"meta" => (int) $item->getDamage(),
					"amount" => (int) $event->getLine(3)
			);

			$player->sendMessage($this->getMessage("sell-created", [$item->getName(), (int)$event->getLine(3), ""]));

			$mu = EconomyAPI::getInstance()->getMonetaryUnit();
			$event->setLine(0, $val[0]);
			$event->setLine(1, str_replace(["%MONETARY_UNIT%", "%1"], [$mu, $event->getLine(1)], $val[1]));
			$event->setLine(2, str_replace(["%MONETARY_UNIT%", "%2"], [$mu, $item->getName()], $val[2]));
			$event->setLine(3, str_replace(["%MONETARY_UNIT%", "%3"], [$mu, $event->getLine(3)], $val[3]));
		}
	}

	public function onTouch(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$loc = $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName();
		if(isset($this->sell[$loc])){
			$sell = $this->sell[$loc];
			$player = $event->getPlayer();

			if($player->getGamemode() % 2 === 1){
				$player->sendMessage($this->getMessage("creative-mode"));
				$event->setCancelled();
				return;
			}
			if(!$player->hasPermission("economysell.sell.sell")){
				$player->sendMessage($this->getMessage("no-permission-sell"));
				$event->setCancelled();
				return;
			}
			$cnt = 0;
			foreach($player->getInventory()->getContents() as $item){
				if($item->getID() == $sell["item"] and $item->getDamage() == $sell["meta"]){
					$cnt += $item->getCount();
				}
			}

			if(!isset($sell["itemName"])){
				$item = $this->getItem($sell["item"], $sell["meta"], $sell["amount"]);
				if($item === false){
					$item = $sell["item"].":".$sell["meta"];
				}else{
					$item = $item[0];
				}
				$this->sell[$loc]["itemName"] = $item;
				$sell["itemName"] = $item;
			}
			$now = microtime(true);
			if($this->getConfig()->get("enable-double-tap")){
				if(!isset($this->tap[$player->getName()]) or $now - $this->tap[$player->getName()][1] >= 1.5  or $this->tap[$player->getName()][0] !== $loc){
					$this->tap[$player->getName()] = [$loc, $now];
					$player->sendMessage($this->getMessage("tap-again", [$sell["itemName"], $sell["cost"], $sell["amount"]]));
					return;
				}else{
					unset($this->tap[$player->getName()]);
				}
			}

			if($cnt >= $sell ["amount"]){
				$this->removeItem($player, new Item($sell["item"], $sell["meta"], $sell["amount"]));
				EconomyAPI::getInstance()->addMoney($player, $sell ["cost"], true, "EconomySell");
				$player->sendMessage($this->getMessage("sold-item", array($sell ["amount"], $sell ["item"].":".$sell ["meta"], $sell ["cost"] )));
			}else{
				$player->sendMessage($this->getMessage("no-item"));
			}
			$event->setCancelled(true);
			if($event->getItem()->canBePlaced()){
				$this->placeQueue [$player->getName()] = true;
			}
		}
	}

	public function onPlace(BlockPlaceEvent $event){
		$username = $event->getPlayer()->getName();
		if(isset($this->placeQueue [$username])){
			$event->setCancelled(true);
			unset($this->placeQueue [$username]);
		}
	}

	public function onBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		if(isset($this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economysell.sell.remove")){
				$player->sendMessage($this->getMessage("no-permission-break"));
				$event->setCancelled(true);
				return;
			}
			$this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()] = null;
			unset($this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()]);
			$player->sendMessage($this->getMessage("removed-sell"));
		}
	}
	public function checkTag($line1){
		foreach($this->sellSign->getAll() as $tag => $val){
			if($tag == $line1){
				return $val;
			}
		}
		return false;
	}

	public function removeItem($sender, $getitem){
		$getcount = $getitem->getCount();
		if($getcount <= 0)
			return;
		for($index = 0; $index < $sender->getInventory()->getSize(); $index ++){
			$setitem = $sender->getInventory()->getItem($index);
			if($getitem->getID() == $setitem->getID() and $getitem->getDamage() == $setitem->getDamage()){
				if($getcount >= $setitem->getCount()){
					$getcount -= $setitem->getCount();
					$sender->getInventory()->setItem($index, Item::get(Item::AIR, 0, 1));
				}else if($getcount < $setitem->getCount()){
					$sender->getInventory()->setItem($index, Item::get($getitem->getID(), 0, $setitem->getCount() - $getcount));
					break;
				}
			}
		}
	}
}

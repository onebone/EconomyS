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

namespace onebone\economypshop;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use onebone\itemcloud\MainClass;
use onebone\itemcloud\ItemCloud;

use onebone\economyapi\EconomyAPI;

class EconomyPShop extends PluginBase implements Listener{
	private $placeQueue, $shop, $shopText, $lang, $tap;

	/**
	 * @var MainClass
	 */
	private $itemcloud;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		$this->saveResource("ShopText.yml");
		$this->saveResource("language.properties");
		$this->saveDefaultConfig();
		
		$this->shop = (new Config($this->getDataFolder()."Shops.yml", Config::YAML))->getAll();
		$this->shopText = (new Config($this->getDataFolder()."ShopText.yml", Config::YAML));
		$this->lang = (new Config($this->getDataFolder()."language.properties", Config::PROPERTIES));
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->itemcloud = MainClass::getInstance();
		
		$this->tap = [];
		$this->placeQueue = [];
	}
	
	public function onDisable(){
		$file = new Config($this->getDataFolder()."Shops.yml", Config::YAML);
		$file->setAll($this->shop);
		$file->save();
	}

	public function onSignChange(SignChangeEvent $event){
		$line = $event->getLines();
		if(($val = $this->getTag($line[0])) !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economypshop.shop.create")){
				$player->sendMessage($this->getMessage("no-permission-create-shop"));
				return;
			}
			
			$money = EconomyAPI::getInstance()->myMoney($player->getName());
			if($money < $this->getConfig()->get("shop-tax")){
				$player->sendMessage($this->getMessage("no-shop-tax"));
				return;
			}
			EconomyAPI::getInstance()->reduceMoney($player->getName(), $this->getConfig()->get("shop-tax"), "EconomyPShop");
			
			$cost = $line[1];
			$item = $line[2];
			$amount = $line[3];
			
			if(!is_numeric($cost) or !is_numeric($amount)){
				$player->sendMessage($this->getMessage("insert-right-format"));
				return;
			}
			
			// Item identify
			$item = $this->getItem($line[2]);
			if($item === false){
				$player->sendMessage($this->getMessage("item-not-support", array($line[2], "", "")));
				return;
			}
			if($item[1] === false){ // Item name found
				$id = explode(":", strtolower($line[2]));
				$line[2] = $item[0];
			}else{
				$tmp = $this->getItem(strtolower($line[2]));
				$id = explode(":", $tmp[0]);
			}
			$id[0] = (int)$id[0];
			if(!isset($id[1])){
				$id[1] = 0;
			}
			// Item identify end
			
			$block = $event->getBlock();
			$this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()] = [
				"x" => $block->getX(),
				"y" => $block->getY(),
				"z" => $block->getZ(),
				"level" => $block->getLevel()->getFolderName(),
				"owner" => $player->getName(),
				"price" => (int) $line[1],
				"item" => (int) $id[0],
				"itemName" => $line[2],
				"meta" => (int) $id[1],
				"amount" => (int) $line[3]
			];
			
			$mu = EconomyAPI::getInstance()->getMonetaryUnit();
			$event->setLine(0, str_replace("%MONETARY_UNIT%", $mu, $val[0]));
			$event->setLine(1, str_replace(["%MONETARY_UNIT%", "%1"], [$mu, $cost], $val[1]));
			$event->setLine(2, str_replace(["%MONETARY_UNIT%", "%2"], [$mu, $line[2]], $val[2]));
			$event->setLine(3, str_replace(["%MONETARY_UNIT%", "%3"], [$mu, $amount], $val[3]));
			
			$player->sendMessage($this->getMessage("shop-created", [$line[2], $cost, $amount]));
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		$loc = $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName();
		if(isset($this->shop[$loc])){
			$player = $event->getPlayer();
			$shop = $this->shop[$loc];
			
			if($shop["owner"] == $player->getName()){
				unset($this->shop[$loc]);
				$player->sendMessage($this->getMessage("shop-removed"));
			}else{
				if($player->hasPermission("economypshop.shop.destroy.others")){
					$player->sendMessage($this->getMessage("shop-others-removed", [$shop["owner"], "%2", "%3"]));
					unset($this->shop[$loc]);
				}else{
					$player->sendMessage($this->getMessage("no-permission-remove-shop"));
					$event->setCancelled();
				}
			}
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$loc = $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName();
		if(isset($this->shop[$loc])){
			$player = $event->getPlayer();
			$shop = $this->shop[$loc];
			
			if($shop["owner"] == $player->getName()){
				$player->sendMessage($this->getMessage("same-player"));
				return;
			}
			
			$now = microtime(true);
			if(!isset($this->tap[$player->getName()]) or $now - $this->tap[$player->getName()][1] >= 1.5  or $this->tap[$player->getName()][0] !== $loc){
				$this->tap[$player->getName()] = [$loc, $now];
				$player->sendMessage($this->getMessage("tap-again", [$shop["itemName"], $shop["price"], $shop["amount"]]));
				return;
			}else{
				unset($this->tap[$player->getName()]);
			}
			
			if(($cloud = $this->itemcloud->getCloudForPlayer($shop["owner"])) instanceof ItemCloud){
				if($shop["amount"] > $cloud->getCount($shop["item"], $shop["meta"])){
					$player->sendMessage($this->getMessage("no-stock"));
				}else{
					if($player->getInventory()->canAddItem(($item = new Item($shop["item"], $shop["meta"], $shop["amount"]))) === false){
						$player->sendMessage($this->getMessage("no-space"));
					}else{
						$api = EconomyAPI::getInstance();
						if($api->myMoney($player) > $shop["price"]){
							$player->getInventory()->addItem($item);
							$api->reduceMoney($player, $shop["price"], true, "EconomyPShop");
							$player->sendMessage($this->getMessage("bought-item", [$shop["item"].":".$shop["meta"], $shop["price"], $shop["amount"]]));
							$cloud->removeItem($shop["item"], $shop["meta"], $shop["amount"]);
							$api->addMoney($shop["owner"], $shop["price"], true, "EconomyPShop");
						}else{
							$player->sendMessage($this->getMessage("no-money"));
						}
					}
				}
			}else{
				$player->sendMessage($this->getMessage("shop-owner-no-account"));
			}
			$event->setCancelled();
			if($event->getItem()->isPlaceable()){
				$this->placeQueue[$player->getName()] = true;
			}
		}
	}
	
	public function getMessage($key, $val = ["%1", "%2", "%3"]){
		if($this->lang->exists($key)){
			return str_replace(["%1", "%2", "%3", "%MONETARY_UNIT%"], [$val[0], $val[1], $val[2], EconomyAPI::getInstance()->getMonetaryUnit()], $this->lang->get($key));
		}
		return "There's no message named \"$key\"";
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$user = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$user])){
			$event->setCancelled();
			unset($this->placeQueue[$user]);
		}
	}
	
	public function getItem($item){ // gets ItemID and ItemName
		$item = strtolower($item);
		$e = explode(":", $item);
		$e[1] = isset($e[1]) ? $e[1] : 0;
		if(array_key_exists($item, ItemList::$items)){
			return array(ItemList::$items[$item], true); // Returns Item ID
		}else{
			foreach(ItemList::$items as $name => $id){
				$explode = explode(":", $id);
				$explode[1] = isset($explode[1]) ? $explode[1]:0;
				if($explode[0] == $e[0] and $explode[1] == $e[1]){
					return array($name, false);
				}
			}
		}
		return false;
	}
	
	public function getTag($firstLine){
		foreach($this->shopText->getAll() as $key => $val){
			if($key == $firstLine){
				return $val;
			}
		}
		return false;
	}
}
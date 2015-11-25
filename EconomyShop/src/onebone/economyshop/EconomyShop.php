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

namespace onebone\economyshop;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\event\block\BlockPlaceEvent;

use onebone\economyapi\EconomyAPI;

class EconomyShop extends PluginBase implements Listener{

	/**
	 * @var array
	 */
	private $shop;

	/**
	 * @var Config
	 */
	private $shopSign;

	/**
	 * @var Config
	 */
	private $lang;

	private $placeQueue;

	/**
	 * @var EconomyShop
	 */
	private static $instance;

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->saveDefaultConfig();

		$this->shop = (new Config($this->getDataFolder()."Shops.yml", Config::YAML))->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->prepareLangPref();
		$this->placeQueue = array();

		self::$instance = $this;
	}

	public function getShops(){
		return $this->shop;
	}

	/**
	 * @param string $locationIndex
	 * @param float|null $price
	 * @param int|null $amount
	 *
	 * @return bool
	 */
	public function editShop($locationIndex, $price = null, $amount = null){
		if(isset($this->shop[$locationIndex])){
			$price = ($price === null) ? $this->shop[$locationIndex]["price"]: $price;
			$amount = ($amount === null) ? $this->shop[$locationIndex]["amount"]:$amount;

			$location = explode(":", $locationIndex);
			$tile = $this->getServer()->getLevelByName($location[3]);
			if($tile instanceof Sign){
				$tag = $tile->getText()[0];
				$data = [];
				foreach($this->shopSign->getAll() as $value){
					if($value[0] == $tag){
						$data = $value;
						break;
					}
				}
				$tile->setText(
					$data[0],
					str_replace("%1", $price, $data[1]),
					$tile->getText()[2],
					str_replace("%3", $amount, $data[3])
				);
			}

			save:
			$this->shop[$locationIndex] = [
				"x" => (int)$location[0],
				"y" => (int)$location[1],
				"z" => (int)$location[2],
				"level" => $location[3],
				"price" => $price,
				"item" => $this->shop[$locationIndex]["item"],
				"meta" => $this->shop[$locationIndex]["meta"],
				"amount" => $amount
			];
			return true;
		}
		return false;
	}

	/**
	 * @return EconomyShop
	 */
	public static function getInstance(){
		return self::$instance;
	}

	public function prepareLangPref(){
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, yaml_parse(stream_get_contents($resource = $this->getResource("language.yml"))));
		@fclose($resource);
		$this->shopSign = new Config($this->getDataFolder()."ShopText.yml", Config::YAML, yaml_parse(stream_get_contents($resource = $this->getResource("ShopText.yml"))));
		@fclose($resource);
	}

	public function onDisable(){
		$config = (new Config($this->getDataFolder()."Shops.yml", Config::YAML));
		$config->setAll($this->shop);
		$config->save();
	}

	public function tagExists($tag){
		foreach($this->shopSign->getAll() as $key => $val){
			if($tag == $key){
				return $val;
			}
		}
		return false;
	}

	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%MONETARY_UNIT%", "%1", "%2", "%3"), array(EconomyAPI::getInstance()->getMonetaryUnit(), $val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "There are no message which has key \"$key\"";
	}

	public function onSignChange(SignChangeEvent $event){
		$result = $this->tagExists($event->getLine(0));
		if($result !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyshop.shop.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			if(!is_numeric($event->getLine(1)) or !is_numeric($event->getLine(3))){
				$player->sendMessage($this->getMessage("wrong-format"));
				return;
			}

			$item = Item::fromString($event->getLine(2));
			if($item === false){
				$player->sendMessage($this->getMessage("item-not-support", array($event->getLine(2), "", "")));
				return;
			}

			$block = $event->getBlock();
			$this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()] = array(
				"x" => $block->getX(),
				"y" => $block->getY(),
				"z" => $block->getZ(),
				"level" => $block->getLevel()->getFolderName(),
				"price" => (int) $event->getLine(1),
				"item" => (int) $item->getID(),
				"itemName" => $item->getName(),
				"meta" => (int) $item->getDamage(),
				"amount" => (int) $event->getLine(3)
			);

			$player->sendMessage($this->getMessage("shop-created", array($item->getID(), $item->getDamage(), $event->getLine(1))));

			$event->setLine(0, $result[0]); // TAG
			$event->setLine(1, str_replace("%1", $event->getLine(1), $result[1])); // PRICE
			$event->setLine(2, str_replace("%2", $item->getName(), $result[2])); // ITEM NAME
			$event->setLine(3, str_replace("%3", $event->getLine(3), $result[3])); // AMOUNT
		}
	}

	public function onPlayerTouch(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$loc = $block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName();
		if(isset($this->shop[$loc])){
			$shop = $this->shop[$loc];
			$player = $event->getPlayer();
			if($player->getGamemode() % 2 == 1){
				$player->sendMessage($this->getMessage("invalid-gamemode"));
				$event->setCancelled();
				return;
			}
			if(!$player->hasPermission("economyshop.shop.buy")){
				$player->sendMessage($this->getMessage("no-permission-buy"));
				$event->setCancelled();
				return;
			}

			if(!$player->getInventory()->canAddItem(Item::get($shop["item"], $shop["meta"]))){
				$player->sendMessage($this->getMessage("full-inventory"));
				return;
			}

			$money = EconomyAPI::getInstance()->myMoney($player);
			if($shop["price"] > $money){
				$player->sendMessage($this->getMessage("no-money-buy", [$shop["item"].":".$shop["meta"], $shop["price"], "%3"]));
				$event->setCancelled(true);
				if($event->getItem()->canBePlaced()){
					$this->placeQueue[$player->getName()] = true;
				}
				return;
			}else{
				if(!isset($shop["itemName"])){
					$item = $this->getItem($shop["item"], $shop["meta"], $shop["amount"]);
					if($item === false){
						$item = $shop["item"].":".$shop["meta"];
					}else{
						$item = $item[0];
					}
					$this->shop[$loc]["itemName"] = $item;
					$shop["itemName"] = $item;
				}
				$now = microtime(true);
				if($this->getConfig()->get("enable-double-tap")){
					if(!isset($this->tap[$player->getName()]) or $now - $this->tap[$player->getName()][1] >= 1.5  or $this->tap[$player->getName()][0] !== $loc){
						$this->tap[$player->getName()] = [$loc, $now];
						$player->sendMessage($this->getMessage("tap-again", [$shop["itemName"], $shop["price"], $shop["amount"]]));
						return;
					}else{
						unset($this->tap[$player->getName()]);
					}
				}

				$player->getInventory()->addItem(new Item($shop["item"], $shop["meta"], $shop["amount"]));
				EconomyAPI::getInstance()->reduceMoney($player, $shop["price"], true, "EconomyShop");
				$player->sendMessage($this->getMessage("bought-item", [$shop["amount"], $shop["itemName"], $shop["price"]]));
				$event->setCancelled(true);
				if($event->getItem()->canBePlaced()){
					$this->placeQueue[$player->getName()] = true;
				}
			}
		}
	}

	public function onBreakEvent(BlockBreakEvent $event){
		$block = $event->getBlock();
		if(isset($this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()])){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyshop.shop.remove")){
				$player->sendMessage($this->getMessage("no-permission-break"));
				$event->setCancelled(true);
				return;
			}
			$this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()] = null;
			unset($this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getFolderName()]);
			$player->sendMessage($this->getMessage("removed-shop"));
		}
	}

	public function onPlaceEvent(BlockPlaceEvent $event){
		$username = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$username])){
			$event->setCancelled(true);
			unset($this->placeQueue[$username]);
		}
	}
}

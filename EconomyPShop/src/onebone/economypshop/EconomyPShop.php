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

namespace onebone\economypshop;

use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityTeleportEvent;

use onebone\economypshop\item\ItemDisplayer;

class EconomyPShop extends PluginBase implements Listener{
	private $placeQueue, $shop, $shopText, $lang, $tap, $queue, $items;

	/**
	 * @var \onebone\itemcloud\MainClass
	 */
	private $itemcloud;

	/**
	 * @throws \TypeError
	 */
	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}
		if(!class_exists("\\onebone\\itemcloud\\MainClass", false)){
			$this->getLogger()->critical("[DEPENDENCY] Please install ItemCloud plugin to use PShop plugin.");
			return;
		}
		if(!class_exists("\\onebone\\economyapi\\EconomyAPI", false)){
			$this->getLogger()->critical("[DEPENDENCY] Please install EconomyAPI plugin to use PShop plugin.");
			return;
		}

		$this->saveResource("ShopText.yml");
		$this->saveResource("language.properties");
		$this->saveDefaultConfig();

		$this->shop = (new Config($this->getDataFolder() . "Shops.yml", Config::YAML))->getAll();
		$this->shopText = (new Config($this->getDataFolder() . "ShopText.yml", Config::YAML));
		$this->lang = (new Config($this->getDataFolder() . "language.properties", Config::PROPERTIES));
		if(!$this->lang->exists("added-queue")){
			$langArr = $this->lang->getAll();
			$langArr["added-queue"] = "Touch any block to create a PShop.";
			$this->lang->setAll($langArr);
			$this->lang->save();
		}
		$levels = [];
		foreach($this->shop as $shop){
			if(!isset($shop[10]) or $shop[10] !== -2){
				$level = $shop["level"] ?? $shop[3];
				if(!isset($levels[$level])){
					$levels[$level] = $this->getServer()->getLevelByName($level);
				}
				$pos = new Position($shop["x"] ?? $shop[0], $shop["y"] ?? $shop[1], $shop["z"] ?? $shop[2], $levels[$level]);
				$display = $pos;
				if(isset($shop[10]) && $shop[10] !== -1){
					$display = $pos->getSide($shop[10]);
				}
				$this->items[$level][] = new ItemDisplayer($display, Itemfactory::get((int) ($shop["item"] ?? $shop[6]), (int) ($shop["meta"] ?? $shop[8]), (int) ($shop["amount"] ?? $shop[9])), $pos);
			}
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->itemcloud = \onebone\itemcloud\MainClass::getInstance();

		$this->tap = [];
		$this->placeQueue = [];
	}

	public function onDisable(){
		if(Server::getInstance()->isRunning()){
			$this->saveShops();
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $params) : bool{
		switch($command->getName()){
			case "pshop":
				switch(strtolower(array_shift($params))){
					case "create":
					case "cr":
					case "c":
						if(!$sender instanceof Player){
							$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
							return true;
						}
						if(!$sender->hasPermission("economypshop.shop.create")){
							$sender->sendMessage($this->getMessage("no-permission-create-shop"));
							return true;
						}
						if(in_array(strtolower($sender->getLevel()->getName()), $this->getConfig()->get("disallow-worlds", []))){
							$sender->sendMessage($this->getMessage("disallowed-world"));
							return true;
						}
						if(isset($this->queue[strtolower($sender->getName())])){
							unset($this->queue[strtolower($sender->getName())]);
							$sender->sendMessage($this->getMessage("removed-queue"));
							return true;
						}

						$money = \onebone\economyapi\EconomyAPI::getInstance()->myMoney($sender->getName());
						if($money < $this->getConfig()->get("shop-tax")){
							$sender->sendMessage($this->getMessage("no-shop-tax"));
							return true;
						}
						\onebone\economyapi\EconomyAPI::getInstance()->reduceMoney($sender->getName(), $this->getConfig()->get("shop-tax"), "EconomyPShop");

						$itemstring = array_shift($params);
						$amount = array_shift($params);
						$cost = array_shift($params);
						$side = array_shift($params);

						if(trim($itemstring) === "" or trim($amount) === "" or trim($cost) === "" or !is_numeric($amount) or !is_numeric($cost)){
							$sender->sendMessage("Usage: /pshop create <item[:damage]> <amount> <price> [side]");
							return true;
						}

						if($cost < 0 or $amount < 1 or (int) $amount != $amount){
							$sender->sendMessage($this->getMessage("wrong-num"));
							return true;
						}

						$item = Item::fromString($itemstring);
						if(!$item instanceof Item){
							$sender->sendMessage($this->getMessage("item-not-support", array($itemstring, "", "")));
							return true;
						}

						if(trim($side) === ""){
							$side = Vector3::SIDE_UP;
						}else{
							switch(strtolower($side)){
								case "up":
								case Vector3::SIDE_UP:
									$side = Vector3::SIDE_UP;
									break;
								case "down":
								case Vector3::SIDE_DOWN:
									$side = Vector3::SIDE_DOWN;
									break;
								case "west":
								case Vector3::SIDE_WEST:
									$side = Vector3::SIDE_WEST;
									break;
								case "east":
								case Vector3::SIDE_EAST:
									$side = Vector3::SIDE_EAST;
									break;
								case "north":
								case Vector3::SIDE_NORTH:
									$side = Vector3::SIDE_NORTH;
									break;
								case "south":
								case Vector3::SIDE_SOUTH:
									$side = Vector3::SIDE_SOUTH;
									break;
								case "shop":
								case -1:
									$side = -1;
									break;
								case "none":
								case -2:
									$side = -2;
									break;
								default:
									$sender->sendMessage($this->getMessage("invalid-side"));
									return true;
							}
						}
						$this->queue[strtolower($sender->getName())] = [
							$itemstring, (int) $amount, $cost, (int) $side
						];
						$sender->sendMessage($this->getMessage("added-queue"));
						return true;
				}
		}
		return false;
	}


	/**
	 * @param BlockBreakEvent $event
	 * @ignoreCancelled true
	 */
	public function onBlockBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		$loc = $block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName();
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
					$player->sendMessage($this->getMessage("shop-removed"));
				}else{
					$player->sendMessage($this->getMessage("no-permission-remove-shop"));
					$event->setCancelled();
					return;
				}
			}
			$this->saveShops();
			foreach($this->items as $level => $arr){
				foreach($arr as $key => $displayer){
					$link = $displayer->getLinked();
					if($link->getLevel() !== null && ($link->getX() === $shop["x"] ?? $shop[0]) && ($link->getY() === $shop["y"] ?? $shop[1]) && ($link->getZ() === $shop["z"] ?? $shop[2]) && $link->getLevel()->getFolderName() === $shop["level"] ?? $shop[3]){
						$displayer->despawnFromAll();
						unset($this->items[$key]);
						break 2;
					}
				}
			}
			if($event->getItem()->canBePlaced()){
				$this->placeQueue[$player->getName()] = true;
			}
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$loc = $block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName();
		$player = $event->getPlayer();
		if(isset($this->shop[$loc])){
			if($player->hasPermission("economypshop.shop.buy")){
				$shop = $this->shop[$loc];

				if($shop["owner"] == $player->getName()){
					$player->sendMessage($this->getMessage("same-player"));
					return;
				}
				if($shop["price"] < 0 or $shop["amount"] < 1){
					$player->sendMessage($this->getMessage("wrong-num"));
					return;
				}

				$now = microtime(true);
				if(!isset($this->tap[$player->getName()]) or $now - $this->tap[$player->getName()][1] >= 1.5 or $this->tap[$player->getName()][0] !== $loc){
					$this->tap[$player->getName()] = [$loc, $now];
					$player->sendMessage($this->getMessage("tap-again", [$shop["itemName"], $shop["price"], $shop["amount"]]));
					return;
				}else{
					unset($this->tap[$player->getName()]);
				}

				if(($cloud = $this->itemcloud->getCloudForPlayer($shop["owner"])) instanceof \onebone\itemcloud\ItemCloud){
					if($shop["amount"] > $cloud->getCount($shop["item"], $shop["meta"])){
						$player->sendMessage($this->getMessage("no-stock"));
					}else{
						if($player->getInventory()->canAddItem(($item = ItemFactory::get($shop["item"], $shop["meta"], $shop["amount"]))) === false){
							$player->sendMessage($this->getMessage("no-space"));
						}else{
							$api = \onebone\economyapi\EconomyAPI::getInstance();
							if($api->myMoney($player) > $shop["price"]){
								$player->getInventory()->addItem($item);
								$api->reduceMoney($player, $shop["price"], true, "EconomyPShop");
								$player->sendMessage($this->getMessage("bought-item", [$shop["item"] . ":" . $shop["meta"], $shop["price"], $shop["amount"]]));
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
				if($event->getItem()->canBePlaced()){
					$this->placeQueue[$player->getName()] = true;
				}
			}else{
				$player->sendMessage($this->getMessage("no-permission-buy"));
			}
		}else{

			$iusername = strtolower($player->getName());

			if(isset($this->queue[$iusername])){
				$signIds = [Item::SIGN, Item::SIGN_POST, Item::WALL_SIGN];
				if(!$this->getConfig()->get("allow-any-block", false) && !in_array($block->getItemId(), $signIds)) {
					$player->sendMessage($this->getMessage("shop-create-allow-any-block"));
					return;
				}
				$queue = $this->queue[$iusername];
				$item = Item::fromString($queue[0]);
				$item->setCount($queue[1]);

				$block = $event->getBlock();
				$this->shop[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = [
					"x" => $block->getX(),
					"y" => $block->getY(),
					"z" => $block->getZ(),
					"level" => $block->getLevel()->getFolderName(),
					"owner" => $player->getName(),
					"price" => (int) $queue[2],
					"item" => (int) $item->getID(),
					"itemName" => $queue[0],
					"meta" => (int) $item->getDamage(),
					"amount" => (int) $queue[1],
					"side" => (int) $queue[3]
				];
				if($queue[3] !== -2){
					$pos = $block;
					if($queue[3] !== -1){
						$pos = $block->getSide($queue[3]);
					}

					$this->items[$pos->getLevel()->getFolderName()][] = ($dis = new ItemDisplayer($pos, $item, $block));
					$dis->spawnToAll($pos->getLevel());
				}
				$this->saveShops();
				$player->sendMessage($this->getMessage("shop-created", [$queue[0], $queue[2], $queue[1]]));
				if($event->getItem()->canBePlaced()){
					$this->placeQueue[$player->getName()] = true;
				}
				unset($this->queue[$iusername]);
				return;
			}
		}
	}

	public function getMessage($key, $val = ["%1", "%2", "%3"]){
		if($this->lang->exists($key)){
			return str_replace(["%1", "%2", "%3", "%MONETARY_UNIT%"], [$val[0], $val[1], $val[2], \onebone\economyapi\EconomyAPI::getInstance()->getMonetaryUnit()], $this->lang->get($key));
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

	public function onPlayerJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();

		if(isset($this->items[$level])){
			foreach($this->items[$level] as $displayer){
				$displayer->spawnTo($player);
			}
		}
	}

	public function onPlayerTeleport(EntityTeleportEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player){
			if(($from = $event->getFrom()->getLevel()) !== ($to = $event->getTo()->getLevel())){
				if($from !== null and isset($this->items[$from->getFolderName()])){
					foreach($this->items[$from->getFolderName()] as $displayer){
						$displayer->despawnFrom($player);
					}
				}
				if($to !== null and isset($this->items[$to->getFolderName()])){
					foreach($this->items[$to->getFolderName()] as $displayer){
						$displayer->spawnTo($player);
					}
				}
			}
		}
	}

	public function saveShops(){
		$file = new Config($this->getDataFolder() . "Shops.yml", Config::YAML);
		$file->setAll($this->shop);
		$file->save();
	}
}

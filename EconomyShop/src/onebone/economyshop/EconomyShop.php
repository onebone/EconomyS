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

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;
use onebone\economyshop\provider\DataProvider;
use onebone\economyshop\provider\YamlDataProvider;

class EconomyShop extends PluginBase implements Listener{
	/**
	 * @var DataProvider
	 */
	private $provider;

	private $lang;

	private $queue = [], $tap = [];

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		$this->saveDefaultConfig();

		$provider = $this->getConfig()->get("data-provider");
		switch(strtolower($provider)){
			case "yaml":
				$this->provider = new YamlDataProvider($this->getDataFolder()."Shops.yml", $this->getConfig()->get("auto-save"));
				break;
			default:
				$this->getLogger()->critical("Invalid data provider was given. EconomyShop will be terminated.");
				return;
		}
		$this->getLogger()->notice("Data provider was set to: ".$this->provider->getProviderName());

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->lang = json_decode((stream_get_contents($rsc = $this->getResource("lang_en.json"))), true);
		@fclose($rsc);
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "shop":
				switch(strtolower(array_shift($params))){
					case "create":
					case "cr":
					case "c":
						if(!$sender instanceof Player){
							$sender->sendMessage(TextFormat::RED."Please run this command in-game.");
							return true;
						}
						$item = array_shift($params);
						$amount = array_shift($params);
						$price = array_shift($params);
						$side = array_shift($params);

						if(trim($item) === "" or trim($amount) === "" or trim($price) === "" or !is_numeric($amount) or !is_numeric($price)){
							$sender->sendMessage("Usage: /shop create <item[:damage]> <amount> <price> [side]");
							return true;
						}

						if(trim($side) === ""){
							$side = Vector3::SIDE_UP;
						}else{
							switch(strtolower($side)){
								case "up": case Vector3::SIDE_UP: $side = Vector3::SIDE_UP;break;
								case "down": case Vector3::SIDE_DOWN: $side = Vector3::SIDE_DOWN;break;
								case "west": case Vector3::SIDE_WEST: $side = Vector3::SIDE_WEST;break;
								case "east": case Vector3::SIDE_EAST: $side = Vector3::SIDE_EAST;break;
								case "north": case Vector3::SIDE_NORTH: $side = Vector3::SIDE_NORTH;break;
								case "south": case Vector3::SIDE_SOUTH: $side = Vector3::SIDE_SOUTH;break;
								case "shop": case -1: $side = -1;break;
								case "none": case -2: $side = -2;break;
								default:
									$sender->sendMessage($this->getMessage("invalid-side"));
									return true;
							}
						}
						$this->queue[strtolower($sender->getName())] = [
							$item, (int)$amount, (int)$price, (int)$side
						];
						$sender->sendMessage($this->getMessage("added-queue"));
						return true;
					case "remove":
					case "rm":
					case "r":
					case "delete":
					case "del":
					case "d":
						if(!$sender instanceof Player){
							$sender->sendMessage(TextFormat::RED."Please run this command in-game.");
							return true;
						}
						return true;
					case "list":

						return true;
				}
				return false;
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$iusername = strtolower($player->getName());

		if(isset($this->queue[$iusername])){
			$queue = $this->queue[$iusername];
			$item = Item::fromString($queue[0]);
			$this->provider->addShop($block, [
				$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getFolderName(),
				$item->getID(), $item->getDamage(), $item->getName(), $queue[1], $queue[2], $queue[3]
			]);
			$player->sendMessage($this->getMessage("shop-created"));
			unset($this->queue[$iusername]);
			return;
		}

		if(($shop = $this->provider->getShop($block)) !== false){
			if ($this->getConfig()->get("enable-double-tap")){
				$now = time();
				if (isset($this->tap[$iusername]) and $now - $this->tap[$iusername] < 1){
					$this->buyItem($player, $shop);
					unset($this->tap[$iusername]);
				}else{
					$this->tap[$iusername] = $now;
					$player->sendMessage($this->getMessage("tap-again"));
				}
			}else{
				$this->buyItem($player, $shop);
			}
		}
	}

	private function buyItem(Player $player, $shop){
		if(!$player instanceof Player){
			return false;
		}

		$money = EconomyAPI::getInstance()->myMoney($player);
		if($money < $shop[8]){
			$player->sendMessage($this->getMessage("no-money", [$shop[8], $shop[6]]));
		}else{
			$item = Item::get($shop[4], $shop[5], $shop[7]);
			if($player->getInventory()->canAddItem($item)){
				$player->getInventory()->addItem($item);
				$player->sendMessage($this->getMessage("bought-item", [$shop[6], $shop[7], $shop[8]]));
			}else{
				$player->sendMessage($this->getMessage("full-inventory"));
			}
		}
	}

	public function getMessage($key, $replacement = []){
		$key = strtolower($key);
		if(isset($this->lang[$key])){
			$search = [];
			$replace = [];
			$this->replaceColors($search, $replace);

			$search[] = "%MONETARY_UNIT%";
			$replace[] = EconomyAPI::getInstance()->getMonetaryUnit();

			for($i = 1; $i <= count($replacement); $i++){
				$search[] = "%".$i;
				$replace[] = $replacement[$i - 1];
			}
			return str_replace($search, $replace, $this->lang[$key]);
		}
		return "Could not find \"$key\".";
	}

	private function replaceColors(&$search = [], &$replace = []){
		$colors = [
			"BLACK" => "0",
			"DARK_BLUE" => "1",
			"DARK_GREEN" => "2",
			"DARK_AQUA" => "3",
			"DARK_RED" => "4",
			"DARK_PURPLE" => "5",
			"GOLD" => "6",
			"GRAY" => "7",
			"DARK_GRAY" => "8",
			"BLUE" => "9",
			"GREEN" => "a",
			"AQUA" => "b",
			"RED" => "c",
			"LIGHT_PURPLE" => "d",
			"YELLOW" => "e",
			"WHITE" => "f",
			"OBFUSCATED" => "k",
			"BOLD" => "l",
			"STRIKETHROUGH" => "m",
			"UNDERLINE" => "n",
			"ITALIC" => "o",
			"RESET" => "r"
		];
		foreach($colors as $color => $code){
			$search[] = "%%".$color."%%";
			$replace[] = TextFormat::ESCAPE.$code;
		}
	}

	public function onDisable(){
		if($this->provider instanceof DataProvider) {
			$this->provider->close();
		}
	}
}
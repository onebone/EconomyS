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

namespace onebone\economyland;

use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\Event;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\event\EventPriority;
use pocketmine\plugin\MethodEventExecutor;

use onebone\economyapi\EconomyAPI;
use onebone\economyland\database\YamlDatabase;
use onebone\economyland\database\SQLiteDatabase;
use onebone\economyland\database\Database;

class EconomyLand extends PluginBase implements Listener{
	/**
	 * @var \onebone\economyland\database\Database;
	 */
	private $db;
	/**
	 * @var Config
	 */
	private $lang;
	private $start, $end;
	private $expire;

	private $placeQueue;

	private static $instance;

	const RET_LAND_OVERLAP = 0;
	const RET_LAND_LIMIT = 1;
	const RET_SUCCESS = 2;

	public function onEnable(){

		if(!static::$instance instanceof EconomyLand){
			static::$instance = $this;
		}

		$this->saveDefaultConfig();

		if(!is_file($this->getDataFolder()."Expire.dat")){
			file_put_contents($this->getDataFolder()."Expire.dat", serialize(array()));
		}
		$this->expire = unserialize(file_get_contents($this->getDataFolder()."Expire.dat"));

		$this->createConfig();

		if(is_numeric($interval = $this->getConfig()->get("auto-save-interval", 10))){
			if($interval > 0){
				$interval = $interval * 1200;
				$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new SaveTask($this), $interval, $interval);
			}
		}

		$this->placeQueue = [];

		$now = time();
		foreach($this->expire as $landId => &$time){
			$time[1] = $now;
			$this->getServer()->getScheduler()->scheduleDelayedTask(new ExpireTask($this, $landId), ($time[0] * 20));
		}

		switch(strtolower($this->getConfig()->get("database-type", "yaml"))){
			case "yaml":
			case "yml":
				$this->db = new YamlDatabase($this->getDataFolder()."Land.yml", $this->getConfig(), $this->getDataFolder()."Land.sqlite3");
				break;
			case "sqlite3":
			case "sqlite":
				$this->db = new SQLiteDatabase($this->getDataFolder()."Land.sqlite3", $this->getConfig(), $this->getDataFolder()."Land.yml");
				break;
			default:
				$this->db = new YamlDatabase($this->getDataFolder()."Land.yml", $this->getConfig(), $this->getDataFolder()."Land.sqlite3");
				$this->getLogger()->alert("Specified database type is unavailable. Database type is YAML.");
		}

		$this->getServer()->getPluginManager()->registerEvent("pocketmine\\event\\block\\BlockPlaceEvent", $this, EventPriority::HIGHEST, new MethodEventExecutor("onPlaceEvent"), $this);
		$this->getServer()->getPluginManager()->registerEvent("pocketmine\\event\\block\\BlockBreakEvent", $this, EventPriority::HIGHEST, new MethodEventExecutor("onBreakEvent"), $this);
		$this->getServer()->getPluginManager()->registerEvent("pocketmine\\event\\player\\PlayerInteractEvent", $this, EventPriority::HIGHEST, new MethodEventExecutor("onPlayerInteract"), $this);
	}

	public function expireLand($landId){
		if(!isset($this->expire[$landId])) return;
		$landId = (int)$landId;

		$info = $this->db->getLandById($landId);
		if($info === false) return;
		$player = $info["owner"];
		if(($player = $this->getServer()->getPlayerExact($player)) instanceof Player){
			$player->sendMessage("[EconomyLand] Your land #$landId has expired.");
		}
		$this->db->removeLandById($landId);
		unset($this->expire[$landId]);
		return;
	}

	public function onDisable(){
        if (Server::getInstance()->isRunning()) {
            $this->save();
            if($this->db instanceof Database){
                $this->db->close();
            }
        }

	}

	public function save(){
		$now = time();
		foreach($this->expire as $landId => $time){
			$this->expire[$landId][0] -= ($now - $time[1]);
		}
		file_put_contents($this->getDataFolder()."Expire.dat", serialize($this->expire));
		if($this->db instanceof Database){
			$this->db->save();
		}
	}

	/**
	 * @return EconomyLand
	 */
	public static function getInstance(){
		return static::$instance;
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $param) : bool{
		switch($cmd->getName()){
			case "startp":
			if(!$sender instanceof Player){
				$sender->sendMessage($this->getMessage("run-cmd-in-game"));
				return true;
			}
			$x = (int) floor($sender->x);
			$z = (int) floor($sender->z);
			$level = $sender->getLevel()->getFolderName();
			$this->start[$sender->getName()] = array("x" => $x, "z" => $z, "level" => $level);
			$sender->sendMessage($this->getMessage("first-position-saved"));
			return true;
			case "endp":
			if(!$sender instanceof Player){
				$sender->sendMessage($this->getMessage("run-cmd-in-game"));
				return true;
			}
			if(!isset($this->start[$sender->getName()])){
				$sender->sendMessage($this->getMessage("set-first-position"));
				return true;
			}
			if($sender->getLevel()->getFolderName() !== $this->start[$sender->getName()]["level"]){
				$sender->sendMessage($this->getMessage("cant-set-position-in-different-world"));
				return true;
			}

			$startX = (int) $this->start[$sender->getName()]["x"];
			$startZ = (int) $this->start[$sender->getName()]["z"];
			$endX = (int) floor($sender->x);
			$endZ = (int) floor($sender->z);
			$this->end[$sender->getName()] = array(
				"x" => $endX,
				"z" => $endZ
			);
			if($startX > $endX){
				$temp = $endX;
				$endX = $startX;
				$startX = $temp;
			}
			if($startZ > $endZ){
				$temp = $endZ;
				$endZ = $startZ;
				$startZ = $temp;
			}
			$startX--;
			$endX++;
			$startZ--;
			$endZ++;
			$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * $this->getConfig()->get("price-per-y-axis", 100);
			$sender->sendMessage($this->getMessage("confirm-buy-land", array($price, "%2", "%3")));

			if(($land = $this->db->checkOverlap($startX, $endX, $startZ, $endZ, $sender->getLevel())) !== false){
				$sender->sendMessage($this->getMessage("confirm-warning", [$land["ID"], "%2", "%3"]));
			}
			return true;
			case "land":
			$sub = array_shift($param);
			switch($sub){
				case "buy":
				if(!$sender->hasPermission("economyland.command.land.buy")){
					$sender->sendMessage($this->getMessage("no-permission-command"));
					return true;
				}
				if(!$sender instanceof Player){
					$sender->sendMessage($this->getMessage("run-cmd-in-game"));
					return true;
				}

				if(in_array($sender->getLevel()->getFolderName(), $this->getConfig()->get("buying-disallowed-worlds", []))){
					$sender->sendMessage($this->getMessage("not-allowed-to-buy"));
					return true;
				}

				$cnt = count($this->db->getLandsByOwner($sender->getName()));

				if(is_numeric($this->getConfig()->get("player-land-limit", "NaN"))){
					if($cnt >= $this->getConfig()->get("player-land-limit", "NaN")){
						$sender->sendMessage($this->getMessage("land-limit", array($cnt, $this->getConfig()->get("player-land-limit", "NaN"), "%3", "%4")));
						return true;
					}
				}
				if(!isset($this->start[$sender->getName()])){
					$sender->sendMessage($this->getMessage("set-first-position"));
					return true;
				}elseif(!isset($this->end[$sender->getName()])){
					$sender->sendMessage($this->getMessage("set-second-position"));
					return true;
				}
				$l = $this->start[$sender->getName()];
				$endp = $this->end[$sender->getName()];
				$startX = (int) floor($l["x"]);
				$endX = (int) floor($endp["x"]);
				$startZ = (int) floor($l["z"]);
				$endZ = (int) floor($endp["z"]);
				if($startX > $endX){
					$backup = $startX;
					$startX = $endX;
					$endX = $backup;
				}
				if($startZ > $endZ){
					$backup = $startZ;
					$startZ = $endZ;
					$endZ = $backup;
				}

				$result = $this->db->checkOverlap($startX, $endX, $startZ, $endZ, $sender->getLevel()->getFolderName());
				if($result){
					$sender->sendMessage($this->getMessage("land-around-here", array($result["owner"], $result["ID"], "%3")));
					return true;
				}
				$price = ((($endX + 1) - ($startX - 1)) - 1) * ((($endZ + 1) - ($startZ - 1)) - 1) * $this->getConfig()->get("price-per-y-axis", 100);
				if(EconomyAPI::getInstance()->reduceMoney($sender, $price, true, "EconomyLand") === EconomyAPI::RET_INVALID){
					$sender->sendMessage($this->getMessage("no-money-to-buy-land"));
					return true;
				}

				$this->db->addLand($startX, $endX, $startZ, $endZ, $sender->getLevel()->getFolderName(), $price, $sender->getName());
				unset($this->start[$sender->getName()], $this->end[$sender->getName()]);
				$sender->sendMessage($this->getMessage("bought-land", array($price, "%2", "%3")));
				break;
				case "list":
				if(!$sender->hasPermission("economyland.command.land.list")){
					$sender->sendMessage($this->getMessage("no-permission-command"));
					return true;
				}
				$page = isset($param[0]) ? (int) $param[0] : 1;

				$land = $this->db->getAll();
				$output = "";
				$max = ceil(count($land) / 5);
				$pro = 1;
				$page = (int)$page;
				$output .= $this->getMessage("land-list-top", array($page, $max, ""));
				$current = 1;
				foreach($land as $l){
					$cur = (int) ceil($current / 5);
					if($cur > $page)
						continue;
					if($pro == 6)
						break;
					if($page === $cur){
						$output .= $this->getMessage("land-list-format", array($l["ID"], (($l["endX"] + 1) - ($l["startX"] - 1) - 1) * (($l["endZ"] + 1) - ($l["startZ"] - 1) - 1 ), $l["owner"]));
						$pro++;
					}
					$current++;
				}
				$sender->sendMessage($output);
				break;
				case "whose":
				if(!$sender->hasPermission("economyland.command.land.whose")){
					$sender->sendMessage($this->getMessage("no-permission-command"));
					return true;
				}
				$player = array_shift($param);
				$alike = true;
				if(str_replace(" ", "", $player) === ""){
					$player = $sender->getName();
					$alike = false;
				}
				if($alike){
					$lands = $this->db->getLandsByKeyword($player);
				}else{
					$lands = $this->db->getLandsByOwner($player);
				}
				$sender->sendMessage("Results from query : $player\n");
				foreach($lands as $info)
					$sender->sendMessage($this->getMessage("land-list-format", array($info["ID"], ($info["endX"] - $info["startX"]) * ($info["endZ"] - $info["startZ"]), $info["owner"])));
				break;
				case "move":
				if(!$sender instanceof Player){
					$sender->sendMessage($this->getMessage("run-cmd-in-game"));
					return true;
				}
				if(!$sender->hasPermission("economyland.command.land.move")){
					$sender->sendMessage($this->getMessage("no-permission-command"));
					return true;
				}
				$num = array_shift($param);
				if(trim($num) == ""){
					$sender->sendMessage("Usage: /land move <land num>");
					return true;
				}
				if(!is_numeric($num)){
					$sender->sendMessage("Usage: /land move <land num>");
					return true;
				}

				$info = $this->db->getLandById($num);
				if($info === false){
					$sender->sendMessage($this->getMessage("no-land-found", array($num, "", "")));
					return true;
				}

				if($info["owner"] !== $sender->getName()){
					if(!$sender->hasPermission("economyland.land.move.others")){
						$sender->sendMessage($this->getMessage("no-permission-move", [$info["ID"], $info["owner"], "%3"]));
						return true;
					}
				}
				$level = $this->getServer()->getLevelByName($info["level"]);
				if(!$level instanceof Level){
					$sender->sendMessage($this->getMessage("land-corrupted", array($num, $info["level"], "")));
					return true;
				}
				$x = (int) ($info["startX"] + (($info["endX"] - $info["startX"]) / 2));
				$z = (int) ($info["startZ"] + (($info["endZ"] - $info["startZ"]) / 2));
				$cnt = 0;
				for($y = 128;; $y--){
					$vec = new Vector3($x, $y, $z);
					if($level->getBlock($vec)->isSolid()){
						$y++;
						break;
					}
					if($cnt === 5){
						break;
					}
					if($y <= 0){
						++$cnt;
						++$x;
						--$z;
						$y = 128;
						continue;
					}
				}
				$sender->teleport(new Position($x + 0.5, $y + 0.1, $z + 0.5, $level));
				$sender->sendMessage($this->getMessage("success-moving", array($num, "", "")));
				return true;
				case "give":
				if(!$sender instanceof Player){
					$sender->sendMessage($this->getMessage("run-cmd-in-game"));
					return true;
				}
				if(!$sender->hasPermission("economyland.command.land.give")){
					$sender->sendMessage($this->getMessage("no-permission-command"));
					return true;
				}
				$player = array_shift($param);
				$landnum = array_shift($param);
				if(trim($player) == "" or trim($landnum) == "" or !is_numeric($landnum)){
					$sender->sendMessage("Usage: /$cmd give <player> <land number>");
					return true;
				}
				$username = $player;
				$player = $this->getServer()->getPlayer($username);
				if(!$player instanceof Player){
					$sender->sendMessage($this->getMessage("player-not-connected", [$username, "%2", "%3"]));
					return true;
				}

				$info = $this->db->getLandById($landnum);
				if($info === false){
					$sender->sendMessage($this->getMessage("no-land-found", array($landnum, "%2", "%3")));
					return true;
				}
				if($sender->getName() !== $info["owner"] and !$sender->hasPermission("economyland.land.give.others")){
					$sender->sendMessage($this->getMessage("not-your-land", array($landnum, "%2", "%3")));
				}else{
					if($sender->getName() === $player->getName()){
						$sender->sendMessage($this->getMessage("cannot-give-land-myself"));
					}else{
						if(is_numeric($this->getConfig()->get("player-land-limit", "NaN"))){
							$cnt = count($this->db->getLandsByOwner($player->getName()));
							if($cnt >= $this->getConfig()->get("player-land-limit", "NaN")){
								$sender->sendMessage($this->getMessage("give-land-limit", array($player->getName(), $cnt, $this->getConfig()->get("player-land-limit", "NaN"), "%4")));
								return true;
							}
						}

						$this->db->setOwnerById($info["ID"], $player->getName());
						$sender->sendMessage($this->getMessage("gave-land", array($landnum, $player->getName(), "%3")));
						$player->sendMessage($this->getMessage("got-land", array($sender->getName(), $landnum, "%3")));
					}
				}
				return true;
				case "invite":
					if(!$sender->hasPermission("economyland.command.land.invite")){
						$sender->sendMessage($this->getMessage("no-permission-command"));
						return true;
					}
					$landnum = array_shift($param);
					$player = array_shift($param);
					if(trim($player) == "" or trim($landnum) == ""){
						$sender->sendMessage("Usage : /land <invite> <land number> <[r:]player>");
						return true;
					}
					if(!is_numeric($landnum)){
						$sender->sendMessage($this->getMessage("land-num-must-numeric", array($landnum, "%2", "%3")));
						return true;
					}
					$info = $this->db->getLandById($landnum);
					if($info === false){
						$sender->sendMessage($this->getMessage("no-land-found", array($landnum, "%2", "%3")));
						return true;
					}elseif($info["owner"] !== $sender->getName()){
						$sender->sendMessage($this->getMessage("not-your-land", array($landnum, "%2", "%3")));
						return true;
					}else{
						if(preg_match('#^[a-zA-Z0-9_]{3,16}$#', $player) == 0){
							$sender->sendMessage($this->getMessage("invalid-invitee", [$player, "%2", "%3"]));
							return true;
						}
						$result = $this->db->addInviteeById($landnum, $player);
						if($result === false){
							$sender->sendMessage($this->getMessage("already-invitee", array($player, "%2", "%3")));
							return true;
						}
						$sender->sendMessage($this->getMessage("success-invite", array($player, $landnum, "%3")));
					}
					return true;
				case "kick":
					if(!$sender->hasPermission("economyland.command.land.invite.remove")){
						$sender->sendMessage($this->getMessage("no-permission-command"));
						return true;
					}
					$landnum = array_shift($param);
					$player = array_shift($param);

					if(trim($player) === ""){
						$sender->sendMessage("Usage: /land kick <land number> <player>");
						return true;
					}
					if(!is_numeric($landnum)){
						$sender->sendMessage($this->getMessage("land-num-must-numeric", array($landnum, "%2", "%3")));
						return true;
					}

					$info = $this->db->getLandById($landnum);
					if($info === false){
						$sender->sendMessage($this->getMessage("no-land-found", array($landnum, "%2", "%3")));
						return true;
					}

					if($sender->hasPermission("economyland.command.land.invite.remove.others") and $info["owner"] !== $sender->getName()
						or $info["owner"] === $sender->getName()){
							$result = $this->db->removeInviteeById($landnum, $player);
							if($result === false){
								$sender->sendMessage($this->getMessage("not-invitee", array($player, $landnum, "%3")));
								return true;
							}
							$sender->sendMessage($this->getMessage("removed-invitee", array($player, $landnum, "%3")));
					}
					return true;
				case "invitee":
					$landnum = array_shift($param);
					if(trim($landnum) == "" or !is_numeric($landnum)){
						$sender->sendMessage("Usage: /land invitee <land number>");
						return true;
					}

					$info = $this->db->getInviteeById($landnum);
					if($info === false){
						$sender->sendMessage($this->getMessage("no-land-found", array($landnum, "%2", "%3")));
						return true;
					}
					$output = "Invitee of land #$landnum : \n";
					$output .= implode(", ", $info);
					$sender->sendMessage($output);
					return true;
				case "here":
				if(!$sender instanceof Player){
					$sender->sendMessage($this->getMessage("run-cmd-in-game"));
					return true;
				}
				$x = $sender->x;
				$z = $sender->z;

				$info = $this->db->getByCoord($x, $z, $sender->getLevel()->getFolderName());
				if($info === false){
					$sender->sendMessage($this->getMessage("no-one-owned"));
					return true;
				}
				$sender->sendMessage($this->getMessage("here-land", array($info["ID"], $info["owner"], "%3")));
				return true;
				default:
				$sender->sendMessage("Usage: ".$cmd->getUsage());
			}
			return true;
			case "landsell":
			$id = array_shift($param);
			switch ($id){
			case "here":
				if(!$sender instanceof Player){
					$sender->sendMessage($this->getMessage("run-cmd-in-game"));
					return true;
				}
				$x = $sender->getX();
				$z = $sender->getZ();

				$info = $this->db->getByCoord($x, $z, $sender->getLevel()->getFolderName());
				if($info === false){
					$sender->sendMessage($this->getMessage("no-one-owned"));
					return true;
				}
				if($info["owner"] !== $sender->getName() and !$sender->hasPermission("economyland.landsell.others")){
					$sender->sendMessage($this->getMessage("not-my-land"));
				}else{
					EconomyAPI::getInstance()->addMoney($sender, $info["price"] / 2);
					$sender->sendMessage($this->getMessage("sold-land", array(($info["price"] / 2), "%2", "%3")));
					//$this->land->exec("DELETE FROM land WHERE ID = {$info["ID"]}");
					$this->db->removeLandById($info["ID"]);
				}
				return true;
			default:
				$p = $id;
				if(is_numeric($p)){
					$info = $this->db->getLandById($p);
					if($info === false){
						$sender->sendMessage($this->getMessage("no-land-found", array($p, "%2", "%3")));
						return true;
					}
					if($info["owner"] === $sender->getName() or $sender->hasPermission("economyland.landsell.others")){
						EconomyAPI::getInstance()->addMoney($sender, ($info["price"] / 2), true, "EconomyLand");
						$sender->sendMessage($this->getMessage("sold-land", array(($info["price"] / 2), "", "")));

						$this->db->removeLandById($p);
					}else{
						$sender->sendMessage($this->getMessage("not-your-land", array($p, $info["owner"], "%3")));
					}
				}else{
					$sender->sendMessage("Usage: /landsell <here|land number>");
				}
			}
			return true;
		}
		return false;
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			$this->permissionCheck($event);
		}
	}

	public function onPlaceEvent(BlockPlaceEvent $event){
		$name = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$name])){
			$event->setCancelled();
			unset($this->placeQueue[$name]);
		}
	}

	public function onBreakEvent(BlockBreakEvent $event){
		$this->permissionCheck($event);
	}

	public function permissionCheck(Event $event){
		/** @var $player Player */
		$player = $event->getPlayer();
		if($event instanceof PlayerInteractEvent){
			$block = $event->getBlock()->getSide($event->getFace());
		}else{
			$block = $event->getBlock();
		}

		$x = $block->getX();
		$z = $block->getZ();
		$level = $block->getLevel()->getFolderName();

		if(in_array($level, $this->getConfig()->get("non-check-worlds", []))){
			return false;
		}

		$info = $this->db->canTouch($x, $z, $level, $player);
		if($info === -1){
			if($this->getConfig()->get("white-world-protection", [])){
				if(in_array($level, $this->getConfig()->get("white-world-protection", [])) and !$player->hasPermission("economyland.land.modify.whiteland")){
					$player->sendMessage($this->getMessage("not-owned"));
					$event->setCancelled();
					if($event->getItem()->canBePlaced()){
						$this->placeQueue[$player->getName()] = true;
					}
					return false;
				}
			}
		}elseif($info !== true){
			$player->sendMessage($this->getMessage("no-permission", array($info["owner"], "", "")));
			$event->setCancelled();
			if($event instanceof PlayerInteractEvent){
				if($event->getItem()->canBePlaced()){
					$this->placeQueue[$player->getName()] = true;
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Adds land to the EconomyLand database
	 *
	 * @var Player|string	$player
	 * @var int						$startX
	 * @var int						$startZ
	 * @var int						$endX
	 * @var int						$endZ
	 * @var Level|string	$level
	 * @var float					$expires
	 *
	 * @return int
	 */
	public function addLand($player, $startX, $startZ, $endX, $endZ, $level, $expires = null, &$id = null){
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if($player instanceof Player){
			$player = $player->getName();
		}

		if(is_numeric($this->getConfig()->get("player-land-limit", "NaN"))){
			$cnt = count($this->db->getLandsByOwner($player));
			if($cnt >= $this->getConfig()->get("player-land-limit", "NaN")){
				return self::RET_LAND_LIMIT;
			}
		}

		if($startX > $endX){
			$tmp = $startX;
			$startX = $endX;
			$endX = $tmp;
		}
		if($startZ > $endZ){
			$tmp = $startZ;
			$startZ = $endZ;
			$endZ = $tmp;
		}
		$startX--;
		$endX++;
		$startZ--;
		$endZ++;

		$result = $this->db->checkOverlap($startX, $endX, $startZ, $endZ, $level);

		if($result !== false and $this->getConfig()->get("allow-overlap", false) === false){
			return self::RET_LAND_OVERLAP;
		}
		$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * $this->getConfig()->get("price-per-y-axis", 100);
		$id = $this->db->addLand($startX, $endX, $startZ, $endZ, $level, $price, $player, $expires);
		if($expires !== null){
			$this->getServer()->getScheduler()->scheduleDelayedTask(new ExpireTask($this, $id), $expires * 1200);
			$this->expire[$id] = array(
				$expires * 60,
				time()
			);
		}
		return self::RET_SUCCESS;
	}

	public function addInvitee($landId, $player){
		return $this->db->addInviteeById($landId, $player);
	}

	public function removeInvitee($landId, $player){
		return $this->db->removeInviteeById($landId, $player);
	}

	public function getLandInfo($landId){
		return $this->db->getLandById($landId);
	}

	public function checkOverlap($startX, $endX, $startZ, $endZ, $level){
		return $this->db->checkOverlap($startX, $endX, $startZ, $endZ, $level);
	}

	public function getMessage($key, $value = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%MONETARY_UNIT%", "%1", "%2", "%3", "\\n"), array(EconomyAPI::getInstance()->getMonetaryUnit(), $value[0], $value[1], $value[2], "\n"), $this->lang->get($key));
		}
		return "Couldn't find message \"$key\"";
	}

	private function createConfig(){
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, array(
			"sold-land" => "The land was sold for %MONETARY_UNIT%%1",
			"not-my-land" => "This is not your land",
			"no-one-owned" => "Nobody owns this land",
			"not-your-land" => "Land number %1 is not your land",
			"no-land-found" => "There is no land number %1",
			"land-corrupted" => "[EconomyLand] The World %2 of Land number %1 is corrupted.",
			"no-permission-move" => "You have no permission to move to land %1. Owner : %2",
			"fail-moving" => "Failed to move to land %1",
			"success-moving" => "Moved to land %1",
			"land-list-top" => "Showing land list page %1 of %2\\n",
			"land-list-format" => "#%1 Area : %2 m^2 | Owner : %3\\n",
			"here-land" => "#%1 This land belongs to %2",
			"land-num-must-numeric" => "Land number must be numeric",
			"not-invitee" => "%1 is not invited to your land",
			"already-invitee" => "Player %1 is already invited to this land",
			"removed-invitee" => " %1 has been uninvited from land %2",
			"invalid-invitee" => "%1 is an invalid name",
			"success-invite" => "%1 is now invited to this land",
			"player-not-connected" => "Player %1 is not connected",
			"cannot-give-land-myself" => "You can't give land to yourself",
			"gave-land" => "Land %1 was given to %2",
			"got-land" => "[EconomyLand] %1 gave you land %2",
			"land-limit" => "You have %1 lands. The limit is %2",
			"give-land-limit" => "%1 has %2 lands. The limit is %3",
			"set-first-position" => "Please set the first position",
			"set-second-position" => "Please set the second position",
			"not-allowed-to-buy" => "Land cannot be bought in this world",
			"land-around-here" => "[EconomyLand] There is ID:%2 land near here. Owner : %1",
			"no-money-to-buy-land" => "You don't have enough money to buy this land",
			"bought-land" => "Land purchased for %MONETARY_UNIT%%1",
			"first-position-saved" => "First position saved",
			"second-position-saved" => "Second position saved",
			"cant-set-position-in-different-world" => "You can't set a position in different world",
			"confirm-buy-land" => "Price: %MONETARY_UNIT%%1\\nBuy this land with /land buy",
			"confirm-warning" => "WARNING: This land seems to overlap with #%1.",
			"no-permission" => "You don't have permission to edit this land. Owner : %1",
			"no-permission-command" => "[EconomyLand] You don't have permissions to use this command.",
			"not-owned" => "[EconomyLand] You must buy land to build here",
			"run-cmd-in-game" => "[EconomyLand] Please run this command in-game."
		));
	}
}

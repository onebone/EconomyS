<?php

namespace EconomyLand;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\Player;

use EconomyAPI\EconomyAPI;

class EconomyLand extends PluginBase implements Listener{
	private $land, $config;
	private $start, $end;
	
	public function onEnable(){
		if(!class_exists("EconomyAPI\\EconomyAPI")){
			$this->getLogger()->severe("Couldn't find EconomyAPI");
			return;
		}
		
		@mkdir($this->getDataFolder());
		$this->land = new \SQLite3($this->getDataFolder()."LandData.sqlite3");
		$this->land->exec("CREATE TABLE IF NOT EXISTS land(
			ID INTEGER PRIMARY KEY AUTOINCREMENT,
			startX INTEGER NOT NULL,
			startZ INTEGER NOT NULL,
			endX INTEGER NOT NULL,
			endZ INTEGER NOT NULL,
			level TEXT NOT NULL,
			owner TEXT NOT NULL,
			invitee TEXT NOT NULL,
			price INTEGER NOT NULL
		);");
		
		$this->createConfig();
	}
	
	public function onCommand(CommandSender $sender, Command $cmd, $label, array $params){
		switch($cmd->getName()){
			case "startp":
			$x = (int) $sender->x;
			$z = (int) $sender->z;
			$level = $sender->getLevel()->getName();
			$this->start[$sender->getName()] = array("x" => $x, "z" => $z, "level" => $level);
			$sender->sendMessage($this->getMessage("first-position-saved"));
			return true;
			case "endp":
			if(!isset($this->start[$sender->getName()])){
				$sender->sendMessage($this->getMessage("set-first-position"));
				return true;
			}
			if($sender->getLevel()->getName() !== $this->start[$sender->getName()]["level"]){
				$sender->sendMessage($this->getMessage("cant-set-position-in-different-world"));
				return true;
			}
			
			$startX = $this->start[$sender->getName()]["x"];
			$startZ = $this->start[$sender->getName()]["z"];
			$endX = (int) $sender->x;
			$endZ = (int) $sender->z;
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
			$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * 100;
			$sender->sendMessage($this->getMessage("confirm-buy-land", array($price, "%2", "%3")));
			return true;
			case "land":
			$sub = array_shift($params);
			switch($sub){
				case "buy":
				$result = $this->land->query("SELECT * FROM land WHERE owner = '{$sender->getName()}'");
				$cnt = 0;
				if(is_numeric($this->config->get("player-land-limit"))){
					while($result->fetchArray(SQLITE3_ASSOC) !== false){
						++$cnt;
						if($cnt >= $this->config->get("player-land-limit")){
							$sender->sendMessage($this->getMessage("land-limit", array($cnt, $this->config->get("player-land-limit"))));
							return true;
						}
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
				$startX = (int) $l["x"];
				$endX = (int) $endp["x"];
				$startZ = (int) $l["z"];
				$endZ = (int) $endp["z"];
				if($startX > $endX){ // startX 가 endX 보다 클 경우 둘이 바꿔치기
					$backup = $startX;
					$startX = $endX;
					$endX = $backup;
				}
				if($startZ > $endZ){ // startZ 가 endZ 보다 클 경우 둘이 바꿔치기
					$backup = $startZ;
					$startZ = $endZ;
					$endZ = $backup;
				}
				$startX--;
				$endX++;
				$startZ--;
				$endZ++;
				$result = $this->land->query("SELECT * FROM land WHERE startX <= $endX AND endX >= $endX AND startZ <= $endZ AND endZ >= $endZ AND level = '{$sender->getLevel()->getName()}'")->fetchArray(SQLITE3_ASSOC);
				if(!is_bool($result)){
					$sender->sendMessage($this->getMessage("land-around-here", array($result["owner"], "", "")));
					return true;
				}
				$price = (($endX - $startX) - 1) * (($endZ - $startZ) - 1) * 100;
				if(EconomyAPI::getInstance()->reduceMoney($sender, $price, true, "EconomyLand") === EconomyAPI::RET_INVALID){
					$sender->sendMessage($this->getMessage("no-money-to-buy-land"));
					return true;
				}
				$this->land->exec("INSERT INTO land (startX, endX, startZ, endZ, owner, level, price, invitee) VALUES ($startX, $endX, $startZ, $endZ, '{$sender->getName()}', '{$this->start[$sender->getName()]["level"]}', $price, ',')");
				unset($this->start[$sender->getName()], $this->end[$sender->getName()]);
				$sender->sendMessage($this->getMessage("bought-land", array($price, "", "")));
				break;
				case "list":
				
				break;
				case "whose":
				
				break;
				case "move":
				
				break;
			}
			return true;
			case "landsell":
			
			return true;
		}
		return false;
	}
	
	public function eventHandler(BlockEvent $event){
		if($event instanceof BlockPlaceEvent or $event instanceof BlockBreakEvent){
			$this->permissionCheck($event);
		}
	}
	
	public function permissionCheck(BlockEvent $event){
		$block = $event->getBlock();
		$player = $event->getPlayer();
		
		$x = $player->x;
		$y = $player->y;
		$z = $player->z;
		$level = $player->getLevel()->getName();
		
		if(in_array($level, $this->config->get("non-check-worlds"))){
			return;
		}
		
		$exist = false;
		$result = $this->land->query("SELECT owner,invitee FROM land WHERE level = '$level' AND endX > $x AND endZ > $z AND startX < $x AND startZ < $z");
		$info = $result->fetchArray(SQLITE3_ASSOC);
		if(!is_array($info)) goto checkLand;
		if($info["owner"] != $player->getName() and !$this->getServer()->isOp(strtolower($player->getName())) and strpos($info["invitee"], $data["player"]->iusername.",") === false){
			$player->sendMessage($this->getMessage("no-permission", array($info["owner"], "", "")));
			$this->setCancelled(true);
		}else{
			$exist = true;
		}
		checkLand:
		if($this->config->get("white-world-protection")){
			if(!$exist and in_array($level, $this->config->get("white-world-protection")) and !$this->getServer()->isOp(strtolower($player->getName()))){
				$player->sendMessage($this->getMessage("not-owned"));
				$this->setCancelled(true);
			}
		}
	}
	
	public function getMessage($key, $value = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3", "\\n"), array($value[0], $value[1], $value[2], "\n"), $this->lang->get($key));
		}
		return "Couldn't find message \"$key\"";
	}
	
	private function createConfig(){
		$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, array(
			"handler-priority" => 10,
			"white-world-protection" => array(),
			"non-check-worlds" => array(),
			"player-land-limit" => "NaN"
		));
		
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, array(
			"sold-land" => "Has been sold the land for $%1",
			"not-my-land" => "Here is not your land",
			"no-one-owned" => "Anyone doesn't have this land",
			"sold-land" => "Has been sold the land for $%1",
			"not-your-land" => "Land number %1 is not your land",
			"no-land-found" => "There's no land numbered %1",
			"land-corrupted" => "Land number %1 is corrupted",
			"fail-moving" => "Failed moving to land %1",
			"success-moving" => "Has been moved to land %1",
			"land-list-top" => "Showing land list page %1 of %2\\n",
			"land-list-format" => "#%1 Width : %2 m^2 | Owner : %3\\n",
			"here-land" => "#%1 Here is %2's land",
			"land-num-must-numeric" => "Land number must numeric",
			"not-invitee" => "%1 is not invitee of your land",
			"already-invitee" => "Player %1 is already invitee of your land",
			"removed-invitee" => "Has been removed %1 from land %2",
			"success-invite" => "%1 is now invitee of your land",
			"player-not-connected" => "Player %1 is not connected",
			"cannot-give-land-myself" => "You can't give your land yourself",
			"gave-land" => "Has been gave land %1 for %2",
			"got-land" => "[EconomyLand] %1 gave you land %2",
			"land-limit" => "You have %1 lands. The limit of land is %2",
			"set-first-position" => "Please set first position",
			"set-second-position" => "Please set second position",
			"land-around-here" => "There are %1's land around here",
			"no-money-to-buy-land" => "You don't have money to buy this land",
			"bought-land" => "Has been bought land for $%1",
			"first-position-saved" => "First position saved",
			"second-position-saved" => "Second position saved",
			"cant-set-position-in-different-world" => "You can't set position in different world",
			"confirm-buy-land" => "Land price : $%1\\nBuy land with command /land buy",
			"no-permission" => "You don't have permission to edit this land. Owner : %1",
			"not-owned" => "You must buy land to edit this block"
		));
	}
}
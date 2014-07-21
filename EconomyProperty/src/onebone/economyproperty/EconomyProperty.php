<?php

namespace onebone\economyproperty;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\block\SignPost;
use pocketmine\block\Air;
use pocketmine\tile\Sign;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Int;

use onebone\economyapi\EconomyAPI;

use onebone\economyland\EconomyLand;

class EconomyProperty extends PluginBase implements Listener{
	/**
	 * @var \SQLite3
	 */
	private $property;

	private $tap, $placeQueue;

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->property = new \SQLite3($this->getDataFolder()."Property.sqlite3");
		$this->property->exec(stream_get_contents($this->getResource("sqlite3.sql")));
		$this->parseOldData();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->saveDefaultConfig();
		$command = $this->getConfig()->get("commands");
		$this->getServer()->getCommandMap()->register("economyproperty", new PropertyCommand($this, $command["command"], $command["pos1"], $command["pos2"], $command["make"]));

		$this->tap = array();
		$this->placeQueue = array();
	}

	private function parseOldData(){
		if(is_file($this->getDataFolder()."Properties.sqlite3")){
			$cnt = 0;
			$property = new \SQLite3($this->getDataFolder()."Properties.sqlite3");
			$result = $property->query("SELECT * FROM Property");
			while(($d = $result->fetchArray(SQLITE3_ASSOC)) !== false){
				$this->property->exec("INSERT INTO Property (x, y, z, price, level, startX, startZ, landX, landZ) VALUES ($d[x], $d[y], $d[z], $d[price], '$d[level]', $d[startX], $d[startZ], $d[landX], $d[landZ])");
				++$cnt;
			}
			$property->close();
			$this->getLogger()->info("Parsed $cnt of old data to new format database.");
			@unlink($this->getDataFolder()."Properties.sqlite3");
		}
	}

	public function onDisable(){
		$this->property->close();
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		$block = $event->getBlock();

		$info = $this->property->query("SELECT * FROM Property WHERE startX <= {$block->getX()} AND landX >= {$block->getX()} AND startZ <= {$block->getZ()} AND landZ >= {$block->getZ()} AND level = '{$block->getLevel()->getName()}'")->fetchArray(SQLITE3_ASSOC);
		if(!is_bool($info)){
			$player = $event->getPlayer();

			if(!($info["x"] === $block->getX() and $info["y"] === $block->getY() and $info["z"] === $block->getZ())){
				if($player->hasPermission("economyproperty.property.modify") === false){
					$event->setCancelled(true);
					if($event->getItem()->isPlaceable()){
						$this->placeQueue[$player->getName()] = true;//$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName();
					}
					$player->sendMessage("You don't have permission to modify property area.");
					return;
				}else{
					return;
				}
			}
			$level = $block->getLevel();
			$tile = $level->getTile($block);
			if(!$tile instanceof Sign){
				$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
				return;
			}
			$now = time();
			if(isset($this->tap[$player->getName()]) and $this->tap[$player->getName()][0] === $block->x.":".$block->y.":".$block->z and ($now - $this->tap[$player->getName()][1]) <= 2){
				if(EconomyAPI::getInstance()->myMoney($player) < $info["price"]){
					$player->sendMessage("You don't have enough money to buy here.");
					return;
				}else{
					$result = EconomyLand::getInstance()->addLand($player->getName(), $info["startX"], $info["startZ"], $info["landX"], $info["landZ"], $info["level"], $info["rentTime"]);
					if($result){
						EconomyAPI::getInstance()->reduceMoney($player, $info["price"], true , "EconomyProperty");
						$player->sendMessage("Successfully bought land.");
						$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
					}else{
						$player->sendMessage("[EconomyProperty] Failed to buy land. Please contact server operator.");
					}
				}
				$level->removeTile($tile);
				$level->setBlock($block, new Air());
				unset($this->tap[$player->getName()]);
			}else{
				$this->tap[$player->getName()] = array($block->x.":".$block->y.":".$block->z, $now);
				$player->sendMessage("[EconomyProperty] Are you sure to buy here? Tap again to confirm.");
				$event->setCancelled(true);
				if($event->getItem()->isPlaceable()){
					$this->placeQueue[$player->getName()] = true;
				}
			}
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		$block = $event->getBlock();
		$username = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$username])){
			$event->setCancelled(true);
			// No message to send cuz it is already sent by InteractEvent
			unset($this->placeQueue[$username]);
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		$info = $this->property->query("SELECT * FROM Property WHERE startX <= {$block->getX()} AND landX >= {$block->getX()} AND startZ <= {$block->getZ()} AND landZ >= {$block->getZ()} AND level = '{$block->getLevel()->getName()}'")->fetchArray(SQLITE3_ASSOC);
		if(is_bool($info) === false){
			$player = $event->getPlayer();
			if($info["x"] === $block->getX() and $info["y"] === $block->getY() and $info["z"] === $block->getZ()){
				if($player->hasPermission("economyproperty.property.remove")){
					$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
					$player->sendMessage("[EconomyProperty] You have removed property area #".$info["landNum"]);
				}else{
					$event->setCancelled(true);
					$player->sendMessage("You don't have permission to modify property area.");
				}
			}else{
				if($player->hasPermission("economyproperty.property.modify") === false){
					$event->setCancelled(true);
					$player->sendMessage("You don't have permission to modify property area.");
				}
			}
		}
	}

	public function registerArea($first, $sec, $level, $price, $expectedY = 64, $rentTime = null, $expectedYaw = 0){
		if(!$level instanceof Level){
			$level = $this->getServer()->getLevelByName($level);
			if(!$level instanceof Level){
				return false;
			}
		}

		if($first[0] > $sec[0]){
			$tmp = $first[0];
			$first[0] = $sec[0];
			$sec[0] = $tmp;
		}
		if($first[1] > $sec[1]){
			$tmp = $first[1];
			$first[1] = $sec[1];
			$sec[1] = $tmp;
		}

		if($this->checkOverlapping($first, $sec, $level)){
			return false;
		}
		$price = round($price, 2);

		$centerx = (int) $first[0] + round((($sec[0] - $first[0]) / 2));
		$centerz = (int) $first[1] + round((($sec[1] - $first[1]) / 2));
		$x = (int) round(($sec[0] - $first[0]));
		$z = (int) round(($sec[1] - $first[1]));
		$y = 0;
		$diff = 256;
		$tmpY = 0;
		$lastBlock = 0;
		for(; $y < 127; $y++){
			$b = $level->getBlock(new Vector3($centerx, $y, $centerz));
			if($b->getID() === 0 and $lastBlock != 0){
				$difference = abs($expectedY - $y);
				if($difference > $diff){ // Finding the closest location with player or something
					$y = $tmpY;
					break;
				}else{
					$tmpY = $y;
					$diff = $difference;
				}
			}
			$lastBlock = $b->getID();
		}
		if($y >= 127){
			$y = $expectedY;
		}

		$sign = new SignPost(floor((($expectedYaw + 180) * 16 / 360) + 0.5) & 0x0F);
		$sign->position(new Position($centerx, $y, $centerz, $level));
		$level->setBlock($sign, $sign);

		$info = $this->property->query("SELECT seq FROM sqlite_sequence")->fetchArray(SQLITE3_ASSOC);
		$tile = new Sign($level->getChunkAt($centerx >> 4, $centerz >> 4), new Compound(false, array(
			new String("id", Sign::SIGN),
			new Int("x", $centerx),
			new Int("y", $y),
			new Int("z", $centerz),
			new String("Text1", ($rentTime === null ? "[PROPERTY]" : "[RENT]")),
			new String("Text2", "Price : $price"),
			new String("Text3", "Blocks : ".($x*$z*128)),
			new String("Text4", ($rentTime === null ? "Property #".$info["seq"] : "Time : ".($rentTime)."min"))
		)));
		$tile->spawnToAll();
		$this->property->exec("INSERT INTO Property (price, x, y, z, level, startX, startZ, landX, landZ".($rentTime === null ? "":", rentTime").") VALUES ($price, $centerx, $y, $centerz, '{$level->getName()}', $first[0], $first[1], $sec[0], $sec[1]".($rentTime === null?"":", $rentTime").")");
		return [$centerx, $y, $centerz];
	}

	public function checkOverlapping($first, $sec, $level){
		if($level instanceof Level){
			$level = $level->getName();
		}
		$d = $this->property->query("SELECT * FROM Property WHERE (((startX <= $first[0] AND landX >= $first[0]) AND (startZ <= $first[1] AND landZ >= $first[1])) OR ((startX <= $sec[0] AND landX >= $sec[0]) AND (startZ <= $first[1] AND landZ >= $sec[1]))) AND level = '$level'")->fetchArray(SQLITE3_ASSOC);
		return !is_bool($d);
	}
}
<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
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

namespace onebone\economyproperty;

use onebone\economyapi\EconomyAPI;
use onebone\economyland\EconomyLand;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\block\tile\Sign;
use pocketmine\block\tile\Tile;
use SQLite3;

class EconomyProperty extends PluginBase implements Listener {
	/**
	 * @var SQLite3
	 */
	private $property;

	/**
	 * @var array $touch
	 * key : player name
	 * value : null
	 */
	private $tap, $placeQueue, $touch;

	/**
	 * @var PropertyCommand $command
	 */
	private $command;

	public function onEnable() {
		if(!file_exists($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}

		$this->property = new SQLite3($this->getDataFolder() . "Property.sqlite3");
		$this->property->exec(stream_get_contents($resource = $this->getResource("sqlite3.sql")));
		@fclose($resource);
		$this->parseOldData();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->saveDefaultConfig();
		$command = $this->getConfig()->get("commands");
		$this->command = new PropertyCommand($this, $command["command"], $command["pos1"], $command["pos2"], $command["make"], $command["touchPos"]);
		$this->getServer()->getCommandMap()->register("economyproperty", $this->command);

		$this->tap = [];
		$this->touch = [];
		$this->placeQueue = [];
	}

	private function parseOldData() {
		if(is_file($this->getDataFolder() . "Properties.sqlite3")) {
			$cnt = 0;
			$property = new SQLite3($this->getDataFolder() . "Properties.sqlite3");
			$result = $property->query("SELECT * FROM Property");
			while (($d = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
				$this->property->exec("INSERT INTO Property (x, y, z, price, level, startX, startZ, landX, landZ) VALUES ($d[x], $d[y], $d[z], $d[price], '$d[level]', $d[startX], $d[startZ], $d[landX], $d[landZ])");
				++$cnt;
			}
			$property->close();
			$this->getLogger()->info("Parsed $cnt of old data to new format database.");
			@unlink($this->getDataFolder() . "Properties.sqlite3");
		}
	}

	public function onDisable() {
		$this->property->close();
	}

	public function onBlockTouch(PlayerInteractEvent $event) {
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			return;
		}

		$block = $event->getBlock();
		$pos = $block->getPos();
		$player = $event->getPlayer();

		if(isset($this->touch[$player->getName()])) {
			//	$mergeData[$player->getName()][0] = [(int)$block->getX(), (int)$block->getZ(), $block->getWorld()->getName()];
			$this->command->mergePosition($player->getName(), 0, [(int) $pos->getX(), (int) $pos->getZ(), $pos->getWorld()->getFolderName()]);
			$player->sendMessage("[EconomyProperty] First position has been saved.");
			$event->cancel();
			if($event->getItem()->canBePlaced()) {
				$this->placeQueue[$player->getName()] = true;
			}
			return;
		}

		$info = $this->property->query("SELECT * FROM Property WHERE startX <= {$pos->getX()} AND landX >= {$pos->getX()} AND startZ <= {$pos->getZ()} AND landZ >= {$pos->getZ()} AND level = '{$pos->getWorld()->getFolderName()}'")->fetchArray(SQLITE3_ASSOC);
		if(!is_bool($info)) {
			$pos = $block->getPos();
			if(!($info["x"] === $pos->getX() and $info["y"] === $pos->getY() and $info["z"] === $pos->getZ())) {
				if($player->hasPermission("economyproperty.property.modify") === false) {
					$event->cancel();
					if($event->getItem()->canBePlaced()) {
						$this->placeQueue[$player->getName()] = true;
					}
					$player->sendMessage("#" . $info["landNum"] . " You don't have permission to modify property area.");
					return;
				}else{
					return;
				}
			}
			$level = $pos->getWorld();
			$tile = $level->getTile($pos);
			if(!$tile instanceof Sign) {
				$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
				return;
			}
			$now = time();
			if(isset($this->tap[$player->getName()]) and $this->tap[$player->getName()][0] === $block->x . ":" . $block->y . ":" . $block->z and ($now - $this->tap[$player->getName()][1]) <= 2) {
				if(EconomyAPI::getInstance()->myMoney($player) < $info["price"]) {
					$player->sendMessage("You don't have enough money to buy here.");
					return;
				}else{
					$result = EconomyLand::getInstance()->addLand($player->getName(), $info["startX"], $info["startZ"], $info["landX"], $info["landZ"], $info["level"], $info["rentTime"]);
					switch ($result) {
						case EconomyLand::RET_SUCCESS:
							EconomyAPI::getInstance()->reduceMoney($player, $info["price"], null, null, true);
							$player->sendMessage("Successfully bought land.");
							$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
							break;
						case EconomyLand::RET_LAND_OVERLAP:
							$player->sendMessage("[EconomyProperty] Failed to buy the land because the land is trying to overlap.");
							return;
						case EconomyLand::RET_LAND_LIMIT:
							$player->sendMessage("[EconomyProperty] Failed to buy the land due to land limitation.");
							return;
					}
				}
				$tile->close();
				$level->setBlock($pos, ItemFactory::getInstance()->get(ItemIds::AIR));
				unset($this->tap[$player->getName()]);
			}else{
				$this->tap[$player->getName()] = array($pos->x . ":" . $pos->y . ":" . $pos->z, $now);
				$player->sendMessage("#" . $info["landNum"] . " [EconomyProperty] Are you sure to buy here? Tap again to confirm.");
				$event->cancel();
				if($event->getItem()->canBePlaced()) {
					$this->placeQueue[$player->getName()] = true;
				}
			}
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) {
		$username = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$username])) {
			$event->cancel();
			// No message to send cuz it is already sent by InteractEvent
			unset($this->placeQueue[$username]);
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock();
		$pos = $block->getPos();
		$player = $event->getPlayer();

		if(isset($this->touch[$player->getName()])) {
			//$mergeData[$player->getName()][1] = [(int)$block->getX(), (int)$block->getZ()];
			$this->command->mergePosition($player->getName(), 1, [(int) $pos->getX(), (int) $pos->getZ()]);
			$player->sendMessage("[EconomyProperty] Second position has been saved.");
			$event->cancel();
			return;
		}

		$info = $this->property->query("SELECT * FROM Property WHERE startX <= {$pos->getX()} AND landX >= {$pos->getX()} AND startZ <= {$pos->getZ()} AND landZ >= {$pos->getZ()} AND level = '{$pos->getWorld()->getFolderName()}'")->fetchArray(SQLITE3_ASSOC);
		if(is_bool($info) === false) {
			if($info["x"] === $pos->getX() and $info["y"] === $pos->getY() and $info["z"] === $pos->getZ()) {
				if($player->hasPermission("economyproperty.property.remove")) {
					$this->property->exec("DELETE FROM Property WHERE landNum = $info[landNum]");
					$player->sendMessage("[EconomyProperty] You have removed property area #" . $info["landNum"]);
				}else{
					$event->cancel();
					$player->sendMessage("#" . $info["landNum"] . " You don't have permission to modify property area.");
				}
			}else{
				if($player->hasPermission("economyproperty.property.modify") === false) {
					$event->cancel();
					$player->sendMessage("You don't have permission to modify property area.");
				}
			}
		}
	}

	public function registerArea($first, $sec, $level, $price, $expectedY = 64, $rentTime = null, $expectedYaw = 0){
		if(!$level instanceof World){
			$level = $this->getServer()->getWorldManager()->getWorldByName($level);
			if(!$level instanceof World){

				return false;
			}
		}
		$expectedY = round($expectedY);
		if($first[0] > $sec[0]) {
			$tmp = $first[0];
			$first[0] = $sec[0];
			$sec[0] = $tmp;
		}
		if($first[1] > $sec[1]) {
			$tmp = $first[1];
			$first[1] = $sec[1];
			$sec[1] = $tmp;
		}

		if($this->checkOverlapping($first, $sec, $level)) {
			return false;
		}
		if(EconomyLand::getInstance()->checkOverlap($first[0], $sec[0], $first[1], $sec[1], $level)) {
			return false;
		}

		$price = round($price, 2);

		$centerx = (int) ($first[0] + round(((($sec[0]) - $first[0])) / 2));
		$centerz = (int) ($first[1] + round(((($sec[1]) - $first[1])) / 2));
		$x = (int) round(($sec[0] - $first[0]));
		$z = (int) round(($sec[1] - $first[1]));
		$y = 0;
		$diff = 256;
		$tmpY = 0;
		$lastBlock = 0;
		for (; $y < 127; $y++) {
			$b = $level->getBlock(new Vector3($centerx, $y, $centerz));
			$difference = abs($expectedY - $y);
			if($difference > $diff) { // Finding the closest location with player or something
				$y = $tmpY;
				break;
			}else{
				if(($b->getID() === 0 or $b->canBeReplaced()) and !$lastBlock->canBeReplaced()) {
					$tmpY = $y;
					$diff = $difference;
				}
			}
			$lastBlock = $b;
		}
		if($y >= 126) {
			$y = $expectedY;
		}
		$meta = floor((($expectedYaw + 180) * 16 / 360) + 0.5) & 0x0F;
		$level->setBlock(new Position($centerx, $y, $centerz, $level), Block::get(Item::SIGN_POST, $meta));

		$info = $this->property->query("SELECT seq FROM sqlite_sequence")->fetchArray(SQLITE3_ASSOC);
		$tile = new Sign($level->getChunk($centerx >> 4, $centerz >> 4, false), new CompoundTag(false, [
				"id" => new StringTag("id", Tile::SIGN),
				"x" => new IntTag("x", $centerx),
				"y" => new IntTag("y", $y),
				"z" => new IntTag("z", $centerz),
				"Text1" => new StringTag("Text1", ""),
				"Text2" => new StringTag("Text2", ""),
				"Text3" => new StringTag("Text3", ""),
				"Text4" => new StringTag("Text4", "")
		]));
		$tile->setText($rentTime === null ? "[PROPERTY]" : "[RENT]", "Price : $price", "Blocks : " . ($x * $z * 128), ($rentTime === null ? "Property #" . $info["seq"] : "Time : " . ($rentTime) . "min"));
		$this->property->exec("INSERT INTO Property (price, x, y, z, level, startX, startZ, landX, landZ" . ($rentTime === null ? "" : ", rentTime") . ") VALUES ($price, $centerx, $y, $centerz, '{$level->getName()}', $first[0], $first[1], $sec[0], $sec[1]" . ($rentTime === null ? "" : ", $rentTime") . ")");
		return [$centerx, $y, $centerz];
	}

	public function checkOverlapping($first, $sec, $level){
		if($level instanceof World){
			$level = $level->getFolderName();
		}
		$d = $this->property->query("SELECT * FROM Property WHERE (((startX <= $first[0] AND landX >= $first[0]) AND (startZ <= $first[1] AND landZ >= $first[1])) OR ((startX <= $sec[0] AND landX >= $sec[0]) AND (startZ <= $first[1] AND landZ >= $sec[1]))) AND level = '$level'")->fetchArray(SQLITE3_ASSOC);
		return !is_bool($d);
	}

	/**
	 * @param Player|string $player
	 * @return bool
	 */
	public function switchTouchQueue($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		if(isset($this->touch[$player])) {
			unset($this->touch[$player]);
			return false;
		}else{
			$this->touch[$player] = true;
			return true;
		}
	}

	public function touchQueueExists($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		return isset($this->touch[$player]) === true;
	}

	public function removeProperty($id) {
		$this->property->exec("DELETE FROM Property WHERE landNum = $id");
	}

	public function propertyExists($id) {
		return !is_bool($this->property->query("SELECT * FROM Property WHERE landNum = $id"));
	}
}

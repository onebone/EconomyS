<?php

namespace onebone\economysell;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\tile\Sign;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\item\Item;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\EntityDataPacket;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;

use onebone\economyapi\EconomyAPI;

class EconomySell extends PluginBase implements Listener{
	private $sell;
	private $placeQueue;

	/**
	 * @var Config
	 */
	private $sellSign, $lang;

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->sell = (new Config($this->getDataFolder()."Sell.yml", Config::YAML))->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->prepareLangPref();
		$this->placeQueue = array();
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
			"sell-created" => "Sell center has been created (%1:%2 = $%3)",
			"removed-sell" => "Sell center has been removed",
			"creative-mode" => "You are in creative mode",
			"no-permission-break" => "You don't have permission to break sell center",
			"tap-again" => "Are you sure to sell %1 ($%2)? Tap again to confirm",
			"no-item" => "You have no item to sell",
			"sold-item" => "Has been sold %1 of %2 for $%3"
		));

		$this->sellSign = new Config($this->getDataFolder()."SellSign.yml", Config::YAML, array(
			"sell" => array(
				"[SELL]",
				"$%1",
				"%2",
				"Amount : %3"
			)
		));
	}

	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3"), array($val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "There's no message named \"$key\"";
	}

	public function onSignChange(SignChangeEvent $event){
		$tag = $event->getLine(0);
		if(($result = $this->checkTag($tag)) !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economysell.sell.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			
			if(!is_numeric($event->getLine(1)) or !is_numeric($event->getLine(3))){
				$player->sendMessage($this->getMessage("wrong-format"));
				return;
			}

			$item = $this->getItem($event->getLine(2));
			if($item === false){
				$player->sendMessage($this->getMessage("item-not-support", array($event->getLine(2), "", "")));
				return;
			}
			if($item[1] === false){ // Item name found
				$id = explode(":", strtolower($event->getLine(2)));
				$event->setLine(2, $item[0]);
			}else{
				$tmp = $this->getItem(strtolower($event->getLine(2)));
				$id = explode(":", $tmp[0]);
			}
			$id[0] = (int)$id[0];
			if(!isset($id[1])){
				$id[1] = 0;
			}
			$block = $event->getBlock();
			$this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$player->getLevel()->getName()] = array(
				"x" => $block->getX(),
				"y" => $block->getY(),
				"z" => $block->getZ(),
				"level" => $player->getLevel()->getName(),
				"cost" => (int)$event->getLine(1),
				"item" => (int) $id[0],
				"meta" => (int) $id[1],
				"amount" => (int)$event->getLine(3)
			);
			$player->sendMessage($this->getMessage("sell-created", [$id[0], $id[1], (int)$event->getLine(3)]));
			
			$event->setLine(0, $result[0]); // TAG
			$event->setLine(1, str_replace("%1", $event->getLine(1), $result[1])); // PRICE
			$event->setLine(2, str_replace("%2", $event->getLine(2), $result[2])); // ID AND DAMAGE
			$event->setLine(3, str_replace("%3", $event->getLine(3), $result[3])); // AMOUNT
			
			$this->getLogger()->debug("Sell center has been created at ".$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName());
		}

	}

	public function onTouch(PlayerInteractEvent $event){
		$block = $event->getBlock();
		if(isset($this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
			$sell = $this->sell[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()];
			$player = $event->getPlayer();

			$cnt = 0;
			foreach($player->getInventory()->getContents() as $item){
				if($item->getID() == $sell["item"] and $item->getDamage() == $sell["meta"]){
					$cnt += $item->getCount();
				}
			}
			if($cnt > $sell["amount"]){
				$player->getInventory()->removeItem(new Item($sell["item"], $sell["meta"], $sell["amount"]));
				EconomyAPI::getInstance()->addMoney($player, $sell["cost"], true, "EconomySell");
				$player->sendMessage($this->getMessage("sold-item", array($sell["amount"], $sell["item"].":".$sell["meta"], $sell["cost"])));
			}else{
				$player->sendMessage($this->getMessage("no-item"));
			}
			$event->setCancelled(true);
			if($event->getItem()->isPlaceable()){
				$this->placeQueue[$player->getName()] = true;
			}
		}
	}

	public function onPlace(BlockPlaceEvent $event){
		$username = $event->getPlayer()->getName();
		if(isset($this->placeQueue[$username])){
			$event->setCancelled(true);
			unset($this->placeQueue[$username]);
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

	public function getItem($item){ // gets ItemID and ItemName
		$item = strtolower($item);
		$e = explode(":", $item);
		$e[1] = isset($e[1]) ? $e[1] : 0;
		if(isset(ItemList::$items[$item])){
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
}
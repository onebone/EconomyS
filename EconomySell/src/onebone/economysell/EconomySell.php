<?php

namespace onebone\economysell;

use pocketmine\event\block\BlockBreakEvent;
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

use economyapi\EconomyAPI;

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

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk instanceof EntityDataPacket){
			$player = $event->getPlayer();

			$nbt = new NBT();
			$nbt->read($pk->namedtag);
			$nbt = $nbt->getData();
			$tile = $player->getLevel()->getTile(new Vector3($pk->x, $pk->y, $pk->z));
			if(!$tile instanceof Sign) return;
			if(($val = $this->checkTag($nbt["Text1"])) === false) return;
			if(!$player->hasPermission("economysell.sell.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			if(!is_numeric($nbt["Text2"]) or !is_numeric($nbt["Text4"])){
				$player->sendMessage($this->getMessage("wrong-format"));
				return;
			}

			// Item identify
			$item = $this->getItem($nbt["Text3"]);
			if($item === false){
				$player->sendMessage($this->getMessage("item-not-support", array($nbt["Text3"], "", "")));
				return;
			}
			if($item[1] === false){ // Item name found
				$id = explode(":", strtolower($nbt["Text3"]));
				$nbt["Text3"] = $item[0];
			}else{
				$tmp = $this->getItem(strtolower($nbt["Text3"]));
				$id = explode(":", $tmp[0]);
			}
			$id[0] = (int)$id[0];
			if(!isset($id[1])){
				$id[1] = 0;
			}
			$this->sell[$pk->x.":".$pk->y.":".$pk->z.":".$player->getLevel()->getName()] = array(
				"x" => $pk->x,
				"y" => $pk->y,
				"z" => $pk->z,
				"level" => $player->getLevel()->getName(),
				"cost" => (int) $nbt["Text2"],
				"item" => (int) $id[0],
				"meta" => (int) $id[1],
				"amount" => (int) $nbt["Text4"]
			);

			$player->sendMessage($this->getMessage("sell-created", array($id[0], $id[1], $nbt["Text2"])));

			$n = new NBT();
			$n->setData(new Compound(false, array(
				new String("id", Sign::SIGN),
				new Int("x", $pk->x),
				new Int("y", $pk->y),
				new Int("z", $pk->z),
				new String("Text1", $val[0]),
				new String("Text2", str_replace("%1", $nbt["Text2"], $val[1])),
				new String("Text3", str_replace("%2", $nbt["Text3"], $val[2])),
				new String("Text4", str_replace("%3", $nbt["Text4"], $val[3]))
			)));
			$pk->namedtag = $n->write();
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
}
<?php

namespace economyshop;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
// use pocketmine\event\block\SignChangeEvent;// TODO Uncomment this when the event implemented
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;
use pocketmine\item\Item;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\EntityDataPacket;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;

use economyapi\EconomyAPI;

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

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->shop = (new Config($this->getDataFolder()."Shops.yml", Config::YAML))->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->prepareLangPref();
		$this->placeQueue = array();
	}

	public function prepareLangPref(){
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES, yaml_parse_file($this->getFile()."resources/language.yml"));
		$this->shopSign = new Config($this->getDataFolder()."ShopText.yml", Config::YAML, yaml_parse_file($this->getFile()."resources/ShopText.yml"));
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

	public function getItem($item){ // gets ItemID and ItemName
		$item = strtolower($item);
		$e = explode(":", $item);
		$e[1] = isset($e[1]) ? $e[1] : 0;
		if(array_key_exists($item, ItemList::$items)){
			return array(Itemlist::$items[$item], true); // Returns Item ID
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

	public function getMessage($key, $val = array("%1", "%2", "%3")){
		if($this->lang->exists($key)){
			return str_replace(array("%1", "%2", "%3"), array($val[0], $val[1], $val[2]), $this->lang->get($key));
		}
		return "There are no message which has key \"$key\"";
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk instanceof EntityDataPacket){
			$nbt = new NBT(NBT::LITTLE_ENDIAN);
			$nbt->read($pk->namedtag);
			$nbt = $nbt->getData();
			if($nbt["id"] === Sign::SIGN){
				$player = $event->getPlayer();
				$tile = $player->getLevel()->getTile(new Vector3($pk->x, $pk->y, $pk->z));
				if(!$tile instanceof Sign) return;
				if(($result = $this->tagExists($nbt["Text1"])) !== false){
					if(!$player->hasPermission("economyshop.shop.create")){
						$player->sendMessage($this->getMessage("no-permission-create"));
						return;
					}
				}else{
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
				// Item identify end
				$this->shop[$tile->getX().":".$tile->getY().":".$tile->getZ().":".$tile->getLevel()->getName()] = array(
					"x" => $tile->getX(),
					"y" => $tile->getY(),
					"z" => $tile->getZ(),
					"level" => $tile->getLevel()->getName(),
					"price" => (int) $nbt["Text2"],
					"item" => (int) $id[0],
					"meta" => (int) $id[1],
					"amount" => (int) $nbt["Text4"]
				);
				$player->sendMessage($this->getMessage("shop-created", array($id[0], $id[1], $nbt["Text2"])));
				$n = new NBT();
				$n->setData(new Compound(false, array(
					new String("id", Sign::SIGN),
					new Int("x", $pk->x),
					new Int("y", $pk->y),
					new Int("z", $pk->z),
					new String("Text1", $result[0]),
					new String("Text2", str_replace("%1", $nbt["Text2"], $result[1])),
					new String("Text3", str_replace("%2", $nbt["Text3"], $result[2])),
					new String("Text4", str_replace("%3", $nbt["Text4"], $result[3]))
				)));
				$pk->namedtag = $n->write();
			}
		}
	}
	
	public function onSignChange(/*SignChangeEvent */$event){  // TODO Uncomment when the event implemented
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

			// Item identify
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
			// Item identify end

			$block = $event->getBlock();
			$this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()] = array(
				"x" => $block->getX(),
				"y" => $block->getY(),
				"z" => $block->getZ(),
				"level" => $block->getLevel()->getName(),
				"price" => (int) $event->getLine(1),
				"item" => (int) $id[0],
				"meta" => (int) $id[1],
				"amount" => (int) $event->getLine(3)
			);

			$player->sendMessage($this->getMessage("shop-created", array($id[0], $id[1], $event->getLine(1))));

		//	$d = $this->getData($event->getLine(0));
			$event->setLine(0, $result[0]);
			$event->setLine(1, str_replace("%1", $event->getLine(1), $result[1]));
			$event->setLine(2, str_replace("%2", $event->getLine(2), $result[2]));
			$event->setLine(3, str_replace("%3", $event->getLine(3), $result[3]));
		}
	}

	public function onPlayerTouch(PlayerInteractEvent $event){
		$block = $event->getBlock();
		if(isset($this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
			$shop = $this->shop[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()];
			$player = $event->getPlayer();
			$money = EconomyAPI::getInstance()->myMoney($player);
			if($shop["price"] > $money){
				$player->sendMessage("[EconomyShop] You don't have enough money to buy ".($shop["item"].":".$shop["meta"])." ($$shop[price])");
				$event->setCancelled(true);
				if($event->getItem()->isPlaceable()){
					$this->placeQueue[$player->getName()] = true;
				}
				return;
			}else{
				$player->getInventory()->addItem(new Item($shop["item"], $shop["meta"], $shop["amount"]));
				EconomyAPI::getInstance()->reduceMoney($player, $shop["price"], true, "EconomyShop");
				$player->sendMessage("[EconomyShop] You have bought $shop[item]:$shop[meta] ($$shop[price])");
				$event->setCancelled(true);
				if($event->getItem()->isPlaceable()){
					$this->placeQueue[$player->getName()] = true;
				}
			}
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
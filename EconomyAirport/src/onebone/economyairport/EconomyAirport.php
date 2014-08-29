<?php

namespace onebone\economyairport;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;

use onebone\economyapi\EconomyAPI;

class EconomyAirport extends PluginBase  implements Listener{
	private $airport;

	/**
	 * @var Config
	 */
	private $lang, $tag;

	public function onEnable(){
		@mkdir($this->getDataFolder());

		$this->airport = array();

		file_put_contents($this->getDataFolder()."language.properties", stream_get_contents($this->getResource("language.properties")));
		file_put_contents($this->getDataFolder()."airport.yml", stream_get_contents($this->getResource("airport.yml")));
		$this->lang = new Config($this->getDataFolder()."language.properties", Config::PROPERTIES);
		$this->tag = new Config($this->getDataFolder()."airport.yml", Config::YAML);

		$airportYml = new Config($this->getDataFolder()."AirportData.yml", Config::YAML);
		$this->airport = $airportYml->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable(){
		$airportYml = new Config($this->getDataFolder()."AirportData.yml", Config::YAML);
		$airportYml->setAll($this->airport);
		$airportYml->save();
	}

	public function onSignChange(SignChangeEvent $event){
		if(($data = $this->checkTag($event->getLine(0), $event->getLine(1))) !== false){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.create")){
				$player->sendMessage($this->getMessage("no-permission-create"));
				return;
			}
			$block = $event->getBlock();
			switch($event->getLine(1)){
				case "departure":
					if(!is_numeric($event->getLine(2))){
						$player->sendMessage($this->getMessage("cost-must-be-numeric"));
						break;
					}
					if(trim($event->getLine(3)) === ""){
						$player->sendMessage($this->getMessage("no-target-airport"));
						break;
					}

					foreach($this->airport as $key => $data){
						if($data["type"] === 1 and $key === $event->getLine(3)){
							$targetX = $data[0];
							$targetY = $data[1];
							$targetZ = $data[2];
							$targetLevel = $data[3];
							break;
						}
					}
					if(!isset($targetX)){
						$player->sendMessage($this->getMessage("no-arrival"));
						break;
					}
					$this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()] = array(
						"type" => 0,
						"cost" => ($cost = round($event->getLine(2))),
						"target" => $event->getLine(3),
						"targetX" => $targetX,
						"targetY" => $targetY,
						"targetZ" => $targetZ,
						"targetLevel" => $targetLevel,
						"targetFolder" => $block->getLevel()->getFolderName()
					);
					$event->setLine(0, $data[0]);
					$event->setLine(1, $data[1]);
					$event->setLine(2, str_replace("%1", $cost, $data[2]));
					$event->setLine(3, str_replace("%2", $event->getLine(3), $data[3]));

					$player->sendMessage($this->getMessage("departure-created", [$event->getLine(3), $cost]));
					break;
				case "arrival":
					if(trim($event->getLine(2)) === ""){
						$player->sendMessage($this->getMessage("no-airport-name"));
						break;
					}
					if(strpos( $event->getLine(2), ":")){
						$player->sendMessage($this->getMessage("invalid-airport-name"));
						break;
					}
					$this->airport[$event->getLine(2)] = array(
						$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getFolderName(),
						"type" => 1
					);

					$event->setLine(0, $data[0]);
					$event->setLine(1, $data[1]);
					$event->setLine(2, str_replace("%1", $event->getLine(2), $data[2]));
					$event->setLine(3, "");

					$player->sendMessage($this->getMessage("arrival-created", [$event->getLine(2), "%2"]));
					break;
			}
		}
	}

	public function onBlockTouch(PlayerInteractEvent $event){
		$block = $event->getBlock();
		if(isset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
			$airport = $this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()];
			$player = $event->getPlayer();
			if(isset($this->airport[$airport["target"]])){
				$money = EconomyAPI::getInstance()->myMoney($player);
				if(!$block->getLevel()->getTile(new Vector3($airport["targetX"], $airport["targetY"], $airport["targetZ"], $airport["targetLevel"])) instanceof Sign){
					$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
					unset($this->airport[$airport["target"]]);
					return;
				}
				if($money < $airport["cost"]){
					$player->sendMessage($this->getMessage("no-money", [$airport["cost"], $money]));
				}else{
					EconomyAPI::getInstance()->reduceMoney($player, $airport["cost"], true, "EconomyAirport");
					$player->sendMessage($this->getMessage("thank-you", [$airport["target"], date("Y/m/d h:i")]));
					$player->teleport(new Position($airport["targetX"], $airport["targetY"], $airport["targetZ"], $this->getServer()->getLevelByName($airport["targetFolder"])));
				}
			}else{
				$player->sendMessage($this->getMessage("no-airport", [$airport["target"], "%2"]));
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		if(isset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
			$player = $event->getPlayer();
			if(!$player->hasPermission("economyairport.remove")){
				$player->sendMessage($this->getMessage("no-permission-break"));
				return;
			}
			unset($this->airport[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()]);
			$player->sendMessage($this->getMessage("airport-removed"));
		}
	}

	public function checkTag($firstLine, $secondLine){
		if(!$this->tag->exists($secondLine)){
			return false;
		}
		foreach($this->tag->get($secondLine) as $key => $data){
			if($firstLine === $key){
				return $data;
			}
		}
		return false;
	}

	public function getMessage($key, $value = ["%1", "%2"]){
		if($this->lang->exists($key)){
			return str_replace(["%1", "%2"], [$value[0], $value[1]], $this->lang->get($key));
		}else{
			return "Language with key \"$key\" does not exist";
		}
	}
}
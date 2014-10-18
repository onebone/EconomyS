<?php

namespace onebone\economytax;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;

use onebone\economyapi\EconomyAPI;

class EconomyTax extends PluginBase{

	/**
	 * @var EconomyAPI
	 */
	private $api;

	/**
	 * @var Config
	 */
	private $config;

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->api = EconomyAPI::getInstance();
		$this->config = new Config($this->getDataFolder()."tax.properties", Config::PROPERTIES, array(
			"time-for-tax" => 10,
			"tax-as-percentage" => "",
			"tax-as-money" => 100
		));
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask(array($this, "onSchedule")), $this->config->get("time-for-tax")*1200);
	}

	public function onSchedule(){
		if(($percent = $this->config->get("tax-as-percentage")) !== ""){
			$players = $this->getServer()->getOnlinePlayers();
			foreach($players as $player){
				if($player->hasPermission("economytax.tax.avoid")){
					continue;
				}
				$money = $this->api->myMoney($player);
				$taking = $money * ($percent / 100);
				$this->api->reduceMoney($player, min($money, $taking), true, "EconomyTax");
				$player->sendMessage("Your $$taking has taken by tax.");
			}
		}else{
			$money = $this->config->get("tax-as-money");
			$players = $this->getServer()->getOnlinePlayers();
				foreach($players as $player){
				if($player->hasPermission("economytax.tax.avoid")){
					continue;
				}
				$this->api->reduceMoney($player, min($this->api->myMoney($player), $money), true, "EconomyTax");
				$player->sendMessage("Your $$money has taken by tax.");
			}
		}
	}
}
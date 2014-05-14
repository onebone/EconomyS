<?php

namespace EconomyAPI;

use EconomyAPI\EconomyAPI;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class DebtScheduler extends PluginTask{
	private $api, $username;
	
	public function __construct(EconomyAPI $api, $username){
		$this->api = $api;
		$this->username = $username;
	}
	
	public function run($tick){
		$server = Server::getInstance();
		$player = $server->matchPlayer($this->username);
		if(!$player instanceof Player){
			return;
		}
		$per = $this->api->getConfigurationValue("percent-of-increase-debt");
		$increase = $this->api->myDebt($player) * ($per / 100);
		$this->api->addDebt($player, $increase, true, "DebtScheduler");
		$player->sendMessage($this->api->getMessage("debt-increase", $player, array($this->api->myDebt($player), "", "", "")));
		$this->api->lastTask["debt"][$player->getName()] = time();
	}
}
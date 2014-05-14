<?php

namespace EconomyAPI;

use EconomyAPI\EconomyAPI;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class BankScheduler extends PluginTask{
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
		$per = $this->api->getConfigurationValue("bank-increase-money-rate");
		$increase = this->api->myBankMoney($player) * ($per / 100);
		$this->api->addBankMoney($player, $increase, true, "DebtScheduler");
		$player->sendMessage($this->api->getMessage("bank-credit-increase", $player)));
		$this->api->lastTask["bank"][$player->getName()] = time();
	}
}
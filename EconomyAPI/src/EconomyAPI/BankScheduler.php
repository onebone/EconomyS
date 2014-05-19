<?php

namespace EconomyAPI;

use EconomyAPI\EconomyAPI;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class BankScheduler extends PluginTask{
	private $plugin, $username;
	
	public function __construct(EconomyAPI $plugin, $username){
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->username = $username;
	}
	
	public function onRun($tick){
		$server = Server::getInstance();
		$player = $server->matchPlayer($this->username);
		if(!$player instanceof Player){
			return;
		}
		$per = $this->plugin->getConfigurationValue("bank-increase-money-rate");
		$increase = ($this->plugin->myBankMoney($player) * ($per / 100));
		$this->plugin->addBankMoney($player, $increase, true, "DebtScheduler");
		$player->sendMessage($this->plugin->getMessage("bank-credit-increase", $player->getName()));
		$this->plugin->lastTask["bank"][$player->getName()] = time();
	}
}
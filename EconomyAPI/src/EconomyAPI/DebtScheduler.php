<?php

namespace EconomyAPI;

use EconomyAPI\EconomyAPI;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\Player;

class DebtScheduler extends PluginTask{
	private $plugin, $username;
	
	public function __construct(EconomyAPI $plugin, $username){
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->username = $username;
	}
	
	public function onRun($tick){
		$server = Server::getInstance();
		$player = $server->getPlayerExact($this->username);
		if(!$player instanceof Player){
			return;
		}
		$per = $this->plugin->getConfigurationValue("percent-of-increase-debt");
		$increase = ($this->plugin->myDebt($player) * ($per / 100));
		$this->plugin->addDebt($player, $increase, true, "DebtScheduler");
		$player->sendMessage($this->plugin->getMessage("debt-increase", $player, array($this->plugin->myDebt($player), "", "", "")));
		$this->plugin->lastTask["debt"][$player->getName()] = time();
	}
}
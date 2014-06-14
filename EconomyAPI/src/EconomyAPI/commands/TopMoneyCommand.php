<?php

namespace economyapi\commands;

use pocketmine\command\CommandSender;
use pocketmine\Server;

use economyapi\EconomyAPI;

class TopMoneyCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(EconomyAPI $plugin, $cmd = "topmoney"){
		parent::__construct($cmd, $plugin);
		$this->plugin = $plugin;
		$this->setUsage("/$cmd <page>");
		$this->setDescription("Shows top money list");
		$this->setPermission("economyapi.command.topmoney");
	}
	
	public function execute(CommandSender $sender, $label, array $params){
		if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
			return false;
		}
		
		$page = array_shift($params);
		
		$moneyData = $this->getPlugin()->getAllMoney();
		
		$server = Server::getInstance();
		$banList = $server->getNameBans(); // TODO TopMoney Command
		arsort($moneyData["money"]);
		$n = 1;
		$max = ceil((count($moneyData["money"]) - count($banList->getEntries()) - ($this->getPlugin()->getConfigurationValue("add-op-at-rank") ? 0 : count($server->getOPs()->getAll()))) / 5);
		$page = max(1, $page);
		$page = min($max, $page);
		$page = (int)$page;
		
		$output = "- Showing top money list ($page of $max) -\n";
		$message = ($this->getPlugin()->getMessage("topmoney-format", $sender->getName(), array("%1", "%2", "%3", "%4"))."\n");
		
		foreach($moneyData["money"] as $player => $money){
			if($banList->isBanned($player)) continue;
			if($server->isOp(strtolower($player)) and ($this->getPlugin()->getConfigurationValue("add-op-at-rank") === false)) continue;
			$current = (int)ceil($n / 5);
			if($current === $page){
				$output .= str_replace(array("%1", "%2", "%3"), array($n, $player, $money), $message);
			}elseif($current > $page){
				break;
			}
			++$n;
		}
		$sender->sendMessage($output);
		return true;
	}
}
<?php

namespace EconomyAPI\commands;

use pocketmine\command\CommandSender;
use pocketmine\Server;

class TopMoneyCommand extends EconomyAPICommand{
	private $plugin, $cmd;
	
	public function __construct(\EconomyAPI\EconomyAPI $plugin, $cmd = "topmoney"){
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
		
		$cnt = 0;
		$data = array();
		$banList = Server::getInstance()->getNameBans();
		foreach($moneyData as $p => $m){
			if($banList->isBanned($p)) continue;
			$data[$m["money"]][] = $p;
			++$cnt;
		}
		
		$max = ceil($cnt / 5);
		$page = max(1, $page);
		$page = min($max, $page);
		$page = (int)$page;
		
		krsort($data);
		$n = 1;
		$output = "- Showing top money list ($page of $max) -\n";
		$message = ($this->getPlugin()->getMessage("topmoney-format", $sender->getName(), array("%1", "%2", "%3", "%4"))."\n");
		foreach($data as $money => $players){
			$current = (int)ceil($n / 5);
			foreach($players as $player){
				if($current === $page){
					$output .= str_replace(array("%1", "%2", "%3"), $sender->getName(), array($n, $player, $money), $message);
				}elseif($current > $page){
					break 2;
				}
				++$n;
			}
		}
		$sender->sendMessage($output);
		return true;
	}
}
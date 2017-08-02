<?php

namespace onebone\economyapi\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

if(version_compare(\pocketmine\API_VERSION, "3.0.0-ALPHA7") >= 0){
	abstract class _MyStatusCommand extends Command{
		public function execute(CommandSender $sender, string $label, array $args): bool{
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}else{
	abstract class _MyStatusCommand extends Command{
		public function execute(CommandSender $sender, $label, array $args){
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}

class MyStatusCommand extends _MyStatusCommand{
	private $plugin;

	public function __construct(EconomyAPI $plugin){
		$desc = $plugin->getCommandMessage("mystatus");
		parent::__construct("mystatus", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.mystatus");

		$this->plugin = $plugin;
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool{
		if(!$this->plugin->isEnabled()) return false;
		if(!$this->testPermission($sender)){
			return false;
		}

		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
			return true;
		}

		$money = $this->plugin->getAllMoney();

		$allMoney = 0;
		foreach($money as $m){
			$allMoney += $m;
		}
		$topMoney = 0;
		if($allMoney > 0){
			$topMoney = round((($money[strtolower($sender->getName())] / $allMoney) * 100), 2);
		}

		$sender->sendMessage($this->plugin->getMessage("mystatus-show", [$topMoney], $sender->getName()));
		return true;
	}
}


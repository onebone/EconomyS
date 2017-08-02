<?php

namespace onebone\economyapi\command;

use pocketmine\event\TranslationContainer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

if(version_compare(\pocketmine\API_VERSION, "3.0.0-ALPHA7") >= 0){
	abstract class _MyMoneyCommand extends Command{
		public function execute(CommandSender $sender, string $label, array $args): bool{
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}else{
	abstract class _MyMoneyCommand extends Command{
		public function execute(CommandSender $sender, $label, array $args){
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}

class MyMoneyCommand extends _MyMoneyCommand{
	private $plugin;

	public function __construct(EconomyAPI $plugin){
		$desc = $plugin->getCommandMessage("mymoney");
		parent::__construct("mymoney", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.mymoney");

		$this->plugin = $plugin;
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool{
		if(!$this->plugin->isEnabled()) return false;
		if(!$this->testPermission($sender)){
			return false;
		}

		if($sender instanceof Player){
			$money = $this->plugin->myMoney($sender);
			$sender->sendMessage($this->plugin->getMessage("mymoney-mymoney", [$money]));
			return true;
		}
		$sender->sendMessage(TextFormat::RED."Please run this command in-game.");
		return true;
	}
}


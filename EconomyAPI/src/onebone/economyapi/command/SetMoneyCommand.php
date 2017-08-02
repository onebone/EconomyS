<?php

namespace onebone\economyapi\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\event\TranslationContainer;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

if(version_compare(\pocketmine\API_VERSION, "3.0.0-ALPHA7") >= 0){
	abstract class _SetMoneyCommand extends Command{
		public function execute(CommandSender $sender, string $label, array $args): bool{
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender,string $label, array $args): bool;
	}
}else{
	abstract class _SetMoneyCommand extends Command{
		public function execute(CommandSender $sender, $label, array $args){
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}

class SetMoneyCommand extends _SetMoneyCommand{
	private $plugin;

	public function __construct(EconomyAPI $plugin){
		$desc = $plugin->getCommandMessage("setmoney");
		parent::__construct("setmoney", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.setmoney");

		$this->plugin = $plugin;
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool{
		if(!$this->plugin->isEnabled()) return false;
		if(!$this->testPermission($sender)){
			return false;
		}

		$player = array_shift($params);
		$amount = array_shift($params);

		if(!is_numeric($amount)){
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		if(($p = $this->plugin->getServer()->getPlayer($player)) instanceof Player){
			$player = $p->getName();
		}

		$result = $this->plugin->setMoney($player, $amount);
		switch($result){
			case EconomyAPI::RET_INVALID:
			$sender->sendMessage($this->plugin->getMessage("setmoney-invalid-number", [$amount], $sender->getName()));
			break;
			case EconomyAPI::RET_NO_ACCOUNT:
			$sender->sendMessage($this->plugin->getMessage("player-never-connected", [$player], $sender->getName()));
			break;
			case EconomyAPI::RET_CANCELLED:
			$sender->sendMessage($this->plugin->getMessage("setmoney-failed", [], $sender->getName()));
			break;
			case EconomyAPI::RET_SUCCESS:
			$sender->sendMessage($this->plugin->getMessage("setmoney-setmoney", [$player, $amount], $sender->getName()));

			if($p instanceof Player){
				$p->sendMessage($this->plugin->getMessage("setmoney-set", [$amount], $p->getName()));
			}
			break;
			default:
			$sender->sendMessage("WTF");
		}
		return true;
	}
}

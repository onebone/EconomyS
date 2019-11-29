<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SeeMoneyCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("seemoney");
		parent::__construct("seemoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.seemoney");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		$player = array_shift($params);
		if(trim($player) === "") {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();
		if(($p = $plugin->getServer()->getPlayer($player)) instanceof Player) {
			$player = $p->getName();
		}

		$money = $plugin->myMoney($player);
		if($money !== false) {
			$sender->sendMessage($plugin->getMessage("seemoney-seemoney", [
				$player,
				$money
			], $sender->getName()));
		}else{
			$sender->sendMessage($plugin->getMessage("player-never-connected", [$player], $sender->getName()));
		}
		return true;
	}
}

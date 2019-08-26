<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class MyStatusCommand extends PluginCommand {
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("mystatus");
		parent::__construct("mystatus", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.mystatus");
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		if (!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
			return true;
		}

		$money = $this->plugin->getAllMoney();

		$allMoney = 0;
		foreach ($money as $m) {
			$allMoney += $m;
		}
		$topMoney = 0;
		if ($allMoney > 0) {
			$topMoney = round((($money[strtolower($sender->getName())] / $allMoney) * 100), 2);
		}

		$sender->sendMessage($this->plugin->getMessage("mystatus-show", [$topMoney], $sender->getName()));
		return true;
	}
}


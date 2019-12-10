<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\CommandIssuer;
use onebone\economyapi\event\money\PayMoneyEvent;
use onebone\economyapi\form\AskPayForm;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class PayCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("pay");
		parent::__construct("pay", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.pay");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		if(!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
			return true;
		}

		$player = array_shift($params);
		$amount = array_shift($params);

		if(!is_numeric($amount)) {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();
		if(($p = $plugin->getServer()->getPlayer($player)) instanceof Player) {
			$player = $p->getName();
		}

		if(!$p instanceof Player and $plugin->getPluginConfig()->getAllowPayOffline() === false) {
			$sender->sendMessage($plugin->getMessage("player-not-connected", [$player], $sender->getName()));
			return true;
		}

		if(!$plugin->accountExists($player)) {
			$sender->sendMessage($plugin->getMessage("player-never-connected", [$player], $sender->getName()));
			return true;
		}

		$sender->sendForm(new AskPayForm($plugin, $sender, $p->getName(), $amount, $label, $params));
		return true;
	}
}

<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\money\PayMoneyEvent;
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

		$ev = new PayMoneyEvent($plugin, $sender->getName(), $player, $amount);
		$ev->call();

		$result = EconomyAPI::RET_CANCELLED;
		if(!$ev->isCancelled()) {
			$result = $plugin->reduceMoney($sender, $amount);
		}

		if($result === EconomyAPI::RET_SUCCESS) {
			$plugin->addMoney($player, $amount, true);

			$sender->sendMessage($plugin->getMessage("pay-success", [
				$amount,
				$player
			], $sender->getName()));
			if($p instanceof Player) {
				$p->sendMessage($plugin->getMessage("money-paid", [
					$sender->getName(),
					$amount
				], $sender->getName()));
			}
		}else{
			$sender->sendMessage($plugin->getMessage("pay-failed", [
				$player,
				$amount
			], $sender->getName()));
		}
		return true;
	}
}


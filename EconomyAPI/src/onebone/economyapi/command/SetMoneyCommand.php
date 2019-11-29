<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class SetMoneyCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("setmoney");
		parent::__construct("setmoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.setmoney");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if (!$this->testPermission($sender)) {
			return false;
		}

		$player = array_shift($params);
		$amount = array_shift($params);

		if (!is_numeric($amount)) {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();
		if (($p = $plugin->getServer()->getPlayer($player)) instanceof Player) {
			$player = $p->getName();
		}

		$result = $plugin->setMoney($player, $amount);
		switch ($result) {
			case EconomyAPI::RET_INVALID:
				$sender->sendMessage($plugin->getMessage("setmoney-invalid-number", [$amount], $sender->getName()));
				break;
			case EconomyAPI::RET_NO_ACCOUNT:
				$sender->sendMessage($plugin->getMessage("player-never-connected", [$player], $sender->getName()));
				break;
			case EconomyAPI::RET_CANCELLED:
				$sender->sendMessage($plugin->getMessage("setmoney-failed", [], $sender->getName()));
				break;
			case EconomyAPI::RET_SUCCESS:
				$sender->sendMessage($plugin->getMessage("setmoney-setmoney", [
					$player,
					$amount
				], $sender->getName()));

				if ($p instanceof Player) {
					$p->sendMessage($plugin->getMessage("setmoney-set", [$amount], $p->getName()));
				}
				break;
			default:
				$sender->sendMessage("WTF");
		}
		return true;
	}
}

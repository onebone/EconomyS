<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class TakeMoneyCommand extends PluginCommand {
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("takemoney");
		parent::__construct("takemoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.takemoney");
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

		if (($p = $this->plugin->getServer()->getPlayer($player)) instanceof Player) {
			$player = $p->getName();
		}

		if ($amount < 0) {
			$sender->sendMessage($this->plugin->getMessage("takemoney-invalid-number", [$amount], $sender->getName()));
			return true;
		}

		$result = $this->plugin->reduceMoney($player, $amount);
		switch ($result) {
			case EconomyAPI::RET_INVALID:
				$sender->sendMessage($this->plugin->getMessage("takemoney-player-lack-of-money", [$player, $amount, $this->plugin->myMoney($player)], $sender->getName()));
				break;
			case EconomyAPI::RET_SUCCESS:
				$sender->sendMessage($this->plugin->getMessage("takemoney-took-money", [$player, $amount], $sender->getName()));

				if ($p instanceof Player) {
					$p->sendMessage($this->plugin->getMessage("takemoney-money-taken", [$amount], $sender->getName()));
				}
				break;
			case EconomyAPI::RET_CANCELLED:
				$sender->sendMessage($this->plugin->getMessage("takemoney-failed", [], $sender->getName()));
				break;
			case EconomyAPI::RET_NO_ACCOUNT:
				$sender->sendMessage($this->plugin->getMessage("player-never-connected", [$player], $sender->getName()));
				break;
		}

		return true;
	}
}


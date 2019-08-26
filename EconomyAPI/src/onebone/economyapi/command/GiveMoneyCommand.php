<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class GiveMoneyCommand extends PluginCommand {
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("givemoney");
		parent::__construct("givemoney", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.givemoney");
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool {
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

		$result = $this->plugin->addMoney($player, $amount);
		switch ($result) {
			case EconomyAPI::RET_INVALID:
				$sender->sendMessage($this->plugin->getMessage("givemoney-invalid-number", [$amount], $sender->getName()));
				break;
			case EconomyAPI::RET_SUCCESS:
				$sender->sendMessage($this->plugin->getMessage("givemoney-gave-money", [$amount, $player], $sender->getName()));

				if ($p instanceof Player) {
					$p->sendMessage($this->plugin->getMessage("givemoney-money-given", [$amount], $sender->getName()));
				}
				break;
			case EconomyAPI::RET_CANCELLED:
				$sender->sendMessage($this->plugin->getMessage("request-cancelled", [], $sender->getName()));
				break;
			case EconomyAPI::RET_NO_ACCOUNT:
				$sender->sendMessage($this->plugin->getMessage("player-never-connected", [$player], $sender->getName()));
				break;
		}

		return true;
	}
}

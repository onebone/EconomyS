<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\Command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;

class SetLangCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("setlang");
		parent::__construct("setlang", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.setlang");
	}

	public function execute(CommandSender $sender, string $label, array $params): bool {
		if(!$this->testPermission($sender)) {
			return false;
		}

		$lang = array_shift($params);
		if(trim($lang) === "") {
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();
		if($plugin->setPlayerLanguage($sender->getName(), $lang)) {
			$sender->sendMessage($plugin->getMessage("language-set", [$lang], $sender->getName()));
		}else{
			$sender->sendMessage(TextFormat::RED . "There is no language such as $lang");
		}
		return true;
	}
}


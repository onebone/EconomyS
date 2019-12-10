<?php

namespace onebone\economyapi\command;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;

class EconomyCommand extends PluginCommand {
	public function __construct(EconomyAPI $plugin) {
		$desc = $plugin->getCommandMessage("economy");
		parent::__construct("economy", $plugin);
		$this->setDescription($desc["description"]);
		$this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.economy");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		if(!$this->testPermission($sender)) {
			return false;
		}

		/** @var EconomyAPI $plugin */
		$plugin = $this->getPlugin();

		$mode = strtolower(array_shift($args));
		$val = array_shift($args);

		switch($mode) {
			case 'lang':
			case 'language':
				if(trim($val) === "") {
					$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
					return true;
				}

				if($plugin->setPlayerLanguage($sender->getName(), $val)) {
					$sender->sendMessage($plugin->getMessage("language-set", [$val], $sender->getName()));
				}else{
					$sender->sendMessage(TextFormat::RED . "There is no language such as $val");
				}
				return true;
		}

		$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
		return false;
	}
}

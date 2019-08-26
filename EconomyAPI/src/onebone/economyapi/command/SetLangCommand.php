<?php

namespace onebone\economyapi\command;

use pocketmine\command\Command;
use pocketmine\Command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;

class SetLangCommand extends PluginCommand {
	private $plugin;

	public function __construct(EconomyAPI $plugin){
		$desc = $plugin->getCommandMessage("setlang");
		parent::__construct("setlang", $plugin);
        $this->setDescription($desc["description"]);
        $this->setUsage($desc["usage"]);

		$this->setPermission("economyapi.command.setlang");
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool{
		if(!$this->testPermission($sender)){
			return false;
		}

		$lang = array_shift($params);
		if(trim($lang) === ""){
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return true;
		}

		if($this->plugin->setPlayerLanguage($sender->getName(), $lang)){
			$sender->sendMessage($this->plugin->getMessage("language-set", [$lang], $sender->getName()));
		}else{
			$sender->sendMessage(TextFormat::RED . "There is no language such as $lang");
		}
		return true;
	}
}


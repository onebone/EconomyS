<?php

namespace onebone\economyapi\command;

use pocketmine\command\Command;
use pocketmine\Command\CommandSender;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;

if(version_compare(\pocketmine\API_VERSION, "3.0.0-ALPHA7") >= 0){
	abstract class _SetLangCommand extends Command{
		public function execute(CommandSender $sender, string $label, array $args): bool{
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}else{
	abstract class _SetLangCommand extends Command{
		public function execute(CommandSender $sender, $label, array $args){
			return $this->_execute($sender, $label, $args);
		}

		abstract public function _execute(CommandSender $sender, string $label, array $args): bool;
	}
}

class SetLangCommand extends _SetLangCommand{
	private $plugin;

	public function __construct(EconomyAPI $plugin){
		$desc = $plugin->getCommandMessage("setlang");
		parent::__construct("setlang", $desc["description"], $desc["usage"]);

		$this->setPermission("economyapi.command.setlang");

		$this->plugin = $plugin;
	}

	public function _execute(CommandSender $sender, string $label, array $params): bool{
		if(!$this->plugin->isEnabled()) return false;
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


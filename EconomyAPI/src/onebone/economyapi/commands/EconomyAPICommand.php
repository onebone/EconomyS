<?php

namespace onebone\economyapi\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;

use onebone\economyapi\EconomyAPI;
use pocketmine\Player;

abstract class EconomyAPICommand extends Command implements PluginIdentifiableCommand{
	/** @var EconomyAPI */
    private $owningPlugin;
	
	public function __construct(EconomyAPI $plugin, $name){
		parent::__construct($name);
		$this->owningPlugin = $plugin;
		$this->usageMessage = "";
	}

	public function getPlugin(){
		return $this->owningPlugin;
	}

    public function execute(CommandSender $sender, $label, array $params){
        if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
            return false;
        }

        if($this instanceof InGameCommand and !$sender instanceof Player){
            $sender->sendMessage(InGameCommand::ERROR_MESSAGE);
            return true;
        }

        return $this->exec($sender, $params);
    }

    /**
     * @param CommandSender $sender
     * @param array $params
     * @return bool
     */
    public abstract function exec(CommandSender $sender, array $params);
}
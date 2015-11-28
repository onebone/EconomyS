<?php

namespace onebone\economyshop;

use onebone\economyshop\provider\YamlDataProvider;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

class EconomyShop extends PluginBase implements Listener{
	/**
	 * @var \onebone\economyshop\provider\DataProvider
	 */
	private $provider;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		$this->saveDefaultConfig();

		$provider = $this->getConfig()->get("data-provider");
		switch(strtolower($provider)){
			case "yaml":
				$this->provider = new YamlDataProvider($this->getDataFolder()."Shops.yml", $this->getConfig()->get("auto-save"));
				break;
			default:
				$this->getLogger()->critical("Invalid data provider was given. EconomyShop will be terminated.");
				return;
		}
		$this->getLogger()->notice("Data provider was set to: ".$this->provider->getProviderName());
	}
}
<?php


namespace onebone\economyapi\currency;

use onebone\economyapi\EconomyAPI;
use pocketmine\Player;

class SimpleCurrencySelector implements CurrencySelector {
	private $plugin;

	public function __construct(EconomyAPI $plugin) {
		$this->plugin = $plugin;
	}

	public function getDefaultCurrency(Player $player): Currency {
		return $this->plugin->getDefaultCurrency();
	}
}

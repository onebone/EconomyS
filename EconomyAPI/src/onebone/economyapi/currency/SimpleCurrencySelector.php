<?php


namespace onebone\economyapi\currency;

use onebone\economyapi\EconomyAPI;
use pocketmine\player\Player;

class SimpleCurrencySelector implements CurrencySelector {
	public function __construct(private EconomyAPI $plugin) {

	}

	public function getDefaultCurrency(Player $player): Currency {
		return $this->plugin->getDefaultCurrency();
	}
}

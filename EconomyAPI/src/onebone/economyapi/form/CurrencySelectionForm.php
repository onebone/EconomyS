<?php

namespace onebone\economyapi\form;

use onebone\economyapi\currency\Currency;
use onebone\economyapi\EconomyAPI;
use pocketmine\form\Form;
use pocketmine\player\Player;

class CurrencySelectionForm implements Form {
	private $plugin;
	private $currencies = [];
	private $player;

	/**
	 * @param EconomyAPI $plugin
	 * @param Currency[]  $currencies
	 * @param Player $player
	 */
	public function __construct(EconomyAPI $plugin, array $currencies, Player $player) {
		$this->plugin = $plugin;
		$this->currencies = array_values($currencies);
		$this->player = $player;
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_int($data)) {
			$player->sendMessage($this->plugin->getMessage('economy-currency-form-invalid', $player));
			return;
		}

		if(!isset($this->currencies[$data])) {
			$player->sendMessage($this->plugin->getMessage('economy-currency-form-invalid', $player));
			return;
		}

		$currency = $this->currencies[$data];
		if($this->plugin->setPlayerPreferredCurrency($player, $currency)) {
			$player->sendMessage($this->plugin->getMessage('economy-currency-set', $player, [$currency->getName(), $currency->getSymbol()]));
		}else{
			$player->sendMessage($this->plugin->getMessage('economy-currency-failed', $player, [$currency->getName(), $currency->getSymbol()]));
		}
	}

	public function jsonSerialize() {
		$buttons = [];
		foreach($this->currencies as $currency) {
			if($currency->isExposed() and $currency->isAvailableTo($this->player)) {
				$buttons[] = ['text' => sprintf("%s (%s)", $currency->getName(), $currency->getSymbol())];
			}
		}

		return [
			'type' => 'form',
			'title' => $this->plugin->getMessage('economy-currency-form-title', $this->player),
			'content' => $this->plugin->getMessage('economy-currency-form-content', $this->player),
			'buttons' => $buttons
		];
	}
}

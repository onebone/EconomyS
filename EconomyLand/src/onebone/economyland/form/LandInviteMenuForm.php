<?php

namespace onebone\economyland\form;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Land;
use pocketmine\form\Form;
use pocketmine\Player;

class LandInviteMenuForm implements Form {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land */
	private $land;

	public function __construct(EconomyLand $plugin, Land $land) {
		$this->plugin = $plugin;
		$this->land = $land;
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_int($data)) return;

		if($data === 0) {
			$player->sendForm(new LandInviteForm($this->plugin, $this->land));
		}elseif($data === 1) {
			$player->sendForm(new LandInviteeSelectionForm($this->plugin, $this->land));
		}
	}

	public function jsonSerialize(): array {
		return [
			'type' => 'form',
			'title' => $this->plugin->getMessage('invite-menu-title'),
			'content' => $this->plugin->getMessage('invite-menu-message', [$this->land->getId()]),
			'buttons' => [
				['text' => $this->plugin->getMessage('invite-menu-add')],
				['text' => $this->plugin->getMessage('invite-menu-manage')],
				['text' => $this->plugin->getMessage('invite-menu-remove')]
			]
		];
	}
}

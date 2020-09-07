<?php

namespace onebone\economyland\form;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Invitee;
use onebone\economyland\land\Land;
use pocketmine\form\Form;
use pocketmine\Player;

class LandInviteeSelectionForm implements Form {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land */
	private $land;
	/** @var Invitee[] */
	private $invitee;

	public function __construct(EconomyLand $plugin, Land $land) {
		$this->plugin = $plugin;
		$this->land = $land;
		// Invitee may be changed after form submit, so save invitee to avoid unexpected behaviour
		$this->invitee = $land->getOption()->getAllInvitee();
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_int($data)) return;

		if(!isset($this->invitee[$data])) {
			return; // this should not happen
		}

		$target = $this->invitee[$data];
		$player->sendForm(new LandInviteeManageForm($this->plugin, $this->land, $target));
	}

	public function jsonSerialize() {
		return [
			'type' => 'form',
			'title' => $this->plugin->getMessage('invitee-select-title'),
			'content' => $this->plugin->getMessage('invitee-select-content'),
			'buttons' => array_map(function (Invitee $invitee) {
				return [
					'text' => $invitee->getName()
				];
			}, $this->invitee)
		];
	}
}

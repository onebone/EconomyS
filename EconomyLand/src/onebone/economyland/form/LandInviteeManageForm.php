<?php

namespace onebone\economyland\form;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Invitee;
use onebone\economyland\land\Land;
use pocketmine\form\Form;
use pocketmine\Player;

class LandInviteeManageForm implements Form {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land */
	private $land;
	/** @var Invitee */
	private $invitee;

	public function __construct(EconomyLand $plugin, Land $land, Invitee $invitee) {
		$this->plugin = $plugin;
		$this->land = $land;
		$this->invitee = $invitee;
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_array($data)) return;

		[$_, $allowTouch, $allowPickup] = $data;
		$opt = $this->land->getOption();

		$this->invitee->setAllowTouch($allowTouch);
		$this->invitee->setAllowPickup($allowPickup);

		$opt->setInvitee($this->invitee);
		$this->land->setOption($opt);

		$this->plugin->getLandManager()->setLand($this->land);
		$player->sendMessage($this->plugin->getMessage('invitee-mgr-done', [$this->land->getId(), $this->invitee->getName()]));
	}

	public function jsonSerialize() {
		return [
			'type' => 'custom_form',
			'title' => $this->plugin->getMessage('invitee-mgr-title'),
			'content' => [
				[
					'type' => 'label',
					'text' => $this->plugin->getMessage('invitee-mgr-message', [$this->land->getId()])
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-touch'),
					'default' => $this->invitee->getAllowTouch()
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-pickup'),
					'default' => $this->invitee->getAllowPickup()
				]
			]
		];
	}
}

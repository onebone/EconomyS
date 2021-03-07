<?php

namespace onebone\economyland\form;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Invitee;
use onebone\economyland\land\Land;
use pocketmine\form\Form;
use pocketmine\Player;

class LandInviteForm implements Form {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land */
	private $land;

	public function __construct(EconomyLand $plugin, Land $land) {
		$this->plugin = $plugin;
		$this->land = $land;
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_array($data)) return;

		[$_, $username, $allowTouch, $allowPickup] = $data;

		$option = $this->land->getOption();
		if($option->isInvitee($username)) {
			$player->sendMessage($this->plugin->getMessage('invite-duplicate', [$username, $this->land->getId()]));
			return;
		}

		$option->addInvitee(new Invitee($username, $allowTouch, $allowPickup));
		$this->land->setOption($option);

		$this->plugin->getLandManager()->setLand($this->land);

		$player->sendMessage($this->plugin->getMessage('invite-done', [$username, $this->land->getId()]));
	}

	public function jsonSerialize(): array {
		return [
			'type' => 'custom_form',
			'title' => $this->plugin->getMessage('invite-title'),
			'content' => [
				[
					'type' => 'label',
					'text' => $this->plugin->getMessage('invite-message', [$this->land->getId()])
				],
				[
					'type' => 'input',
					'text' => $this->plugin->getMessage('invite-name')
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-touch'),
					'default' => true
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-pickup'),
					'default' => true
				]
			]
		];
	}
}

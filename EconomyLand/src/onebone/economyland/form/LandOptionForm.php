<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyland\form;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Land;
use pocketmine\form\Form;
use pocketmine\Player;

class LandOptionForm implements Form {
	/** @var EconomyLand */
	private $plugin;
	/** @var Land */
	private $land;

	public function __construct(EconomyLand $plugin, Land $land) {
		$this->plugin = $plugin;
		$this->land = $land;
	}

	public function handleResponse(Player $player, $data): void {
		if($data === null) return;

		$option = $this->land->getOption();

		$option->setAllowIn($data[0]);
		$option->setAllowTouch($data[1]);
		$option->setAllowPickup($data[2]);
		$this->land->setOption($option);

		$this->plugin->getLandManager()->setLand($this->land);
		$player->sendMessage($this->plugin->getMessage('land-option-updated', [$this->land->getId()]));
	}

	public function jsonSerialize() {
		$option = $this->land->getOption();

		return [
			'type' => 'custom_form',
			'title' => $this->plugin->getMessage('option-form-title'),
			'content' => [
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-in'),
					'default' => $option->getAllowIn()
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-touch'),
					'default' => $option->getAllowTouch()
				],
				[
					'type' => 'toggle',
					'text' => $this->plugin->getMessage('option-form-allow-pickup'),
					'default' => $option->getAllowPickup()
				]
			]
		];
	}
}

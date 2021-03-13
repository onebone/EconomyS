<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2021  onebone <me@onebone.me>
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

namespace onebone\economyland\provider;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Invitee;
use onebone\economyland\land\Land;
use onebone\economyland\land\LandOption;
use pocketmine\math\Vector2;

class YamlProvider implements Provider {
	/** @var EconomyLand */
	private $plugin;
	/** @var array */
	private $lands;
	/** @var string */
	private $file;

	public function __construct(EconomyLand $plugin) {
		$this->plugin = $plugin;

		$this->file = $plugin->getDataFolder() . 'Lands.yml';
		if(!is_file($this->file)) {
			yaml_emit_file($this->file, []);
		}
		$this->lands = yaml_parse_file($this->file);
	}

	public function getNewId(): string {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$len = strlen($chars);

		$id = '';
		for($i = 0; $i < 20; $i++) {
			$id .= $chars[rand(0, $len - 1)];
		}
		return $id;
	}

	public function addLand(Land $land): void {
		$this->setLand($land);
	}

	private function buildLand($land): Land {
		$invitee = [];
		foreach($land['invitee'] ?? []  as $item) {
			$invitee[] = new Invitee($item['name'], $item['allowTouch'], $item['allowPickup']);
		}

		return new Land($this->plugin, $land['id'],
			new Vector2($land['startX'], $land['startZ']), new Vector2($land['endX'], $land['endZ']),
			$land['world'], $land['owner'],
			new LandOption($invitee, $land['allowTouch'], $land['allowIn'], $land['allowPickup']));
	}

	public function getMatches(string $id): array {
		$matches = [];

		foreach($this->lands as $key => $land) {
			if(strpos($key, $id) === 0) {
				$matches[] = $this->buildLand($land);
			}
		}

		return $matches;
	}

	public function getLand(string $id): ?Land {
		if(isset($this->lands[$id])) {
			return $this->buildLand($this->lands[$id]);
		}

		return null;
	}

	public function hasLand(string $id): bool {
		return isset($this->lands[$id]);
	}

	public function setLand(Land $land): void {
		$start = $land->getStart();
		$end = $land->getEnd();
		$option = $land->getOption();

		$invitee = $option->getAllInvitee();
		$inviteeSave = [];
		foreach($invitee as $item) {
			$inviteeSave[] = [
				'name' => $item->getName(),
				'allowTouch' => $item->getAllowTouch(),
				'allowPickup' => $item->getAllowPickup()
			];
		}

		$this->lands[$land->getId()] = [
			'id' => $land->getId(),
			'startX' => (int) $start->getX(),
			'endX' => (int) $end->getX(),
			'startZ' => (int) $start->getY(),
			'endZ' => (int) $end->getY(),
			'world' => $land->getWorldName(),
			'owner' => $land->getOwner(),
			'allowIn' => $option->getAllowIn(),
			'allowTouch' => $option->getAllowTouch(),
			'allowPickup' => $option->getAllowPickup(),
			'invitee' => $inviteeSave
		];
	}

	public function getLandByPosition(int $x, int $z, string $worldName): ?Land {
		foreach($this->lands as $land) {
			if($land['world'] === $worldName
			and $land['startX'] <= $x and $x <= $land['endX']
			and $land['startZ'] <= $z and $z <= $land['endZ']) {
				return $this->buildLand($land);
			}
		}

		return null;
	}

	public function getLandsByOwner(string $owner): array {
		$lands = [];

		$owner = strtolower($owner);
		foreach($this->lands as $land) {
			if($land['owner'] === $owner) {
				$lands[] = $this->buildLand($land);
			}
		}

		return $lands;
	}

	public function save(): void {
		yaml_emit_file($this->file, $this->lands, YAML_UTF8_ENCODING);
	}

	public function close(): void {
		$this->save();
	}
}

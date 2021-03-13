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

namespace onebone\economyland\internal;

use onebone\economyland\EconomyLand;
use onebone\economyland\land\Land;
use onebone\economyland\land\LandManager;
use pocketmine\Player;

// WARNING: This utility class is not for public use. Behavior of
// functions may change without notice.
class InternalUtils {
	/**
	 * @param LandManager $landManager
	 * @param Player $player
	 * @param string $id
	 * @return Land[]
	 */
	public static function findUserLand(LandManager $landManager, Player $player, string $id): array {
		return array_filter($landManager->matchLands($id), function($val) use ($player) {
			return $val->getOwner() === strtolower($player->getName());
		});
	}

	public static function getSingleUserLandVerbose(EconomyLand $plugin, Player $player, string $id): ?Land {
		$lands = self::findUserLand($plugin->getLandManager(), $player, $id);

		$count = count($lands);
		if($count > 1) {
			$player->sendMessage($plugin->getMessage('multiple-land-matches', [implode(', ', array_map(function(Land $val) {
				return $val->getId();
			}, $lands))]));
			return null;
		}elseif($count === 0) {
			$player->sendMessage($plugin->getMessage('no-land-match', [$id]));
			return null;
		}

		// only one matching land here
		return $lands[0];
	}
}

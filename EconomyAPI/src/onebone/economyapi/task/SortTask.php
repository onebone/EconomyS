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

namespace onebone\economyapi\task;

use onebone\economyapi\EconomyAPI;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class SortTask extends AsyncTask {
	private $sender, $moneyData, $addOp, $page, $ops, $banList;

	private $max = 0;

	private $topList;

	/**
	 * @param string			$sender
	 * @param array				$moneyData
	 * @param bool				$addOp
	 * @param int				$page
	 * @param array				$ops
	 * @param array				$banList
	 */
	public function __construct(string $sender, array $moneyData, bool $addOp, int $page, array $ops, array $banList) {
		$this->sender = $sender;
		$this->moneyData = $moneyData;
		$this->addOp = $addOp;
		$this->page = $page;
		$this->ops = $ops;
		$this->banList = $banList;
	}

	public function onRun() : void{
		$this->topList = serialize((array)$this->getTopList());
	}

	private function getTopList() {
		$money = (array) $this->moneyData;
		$banList = (array) $this->banList;
		$ops = (array) $this->ops;
		arsort($money);

		$ret = [];

		$n = 1;
		$this->max = ceil((count($money) - count($banList) - ($this->addOp ? 0 : count($ops))) / 5);
		$this->page = (int) min($this->max, max(1, $this->page));

		foreach($money as $p => $m) {
			$p = strtolower($p);
			if(isset($banList[$p])) continue;
			if(isset($this->ops[$p]) and $this->addOp === false) continue;
			$current = (int) ceil($n / 5);
			if($current === $this->page) {
				$ret[$n] = [
					$p,
					$m
				];
			}elseif($current > $this->page) {
				break;
			}
			++$n;
		}
		return $ret;
	}

	public function onCompletion() : void{
		$server = Server::getInstance();
		if($this->sender === "CONSOLE" or ($player = $server->getPlayerExact($this->sender)) instanceof Player){ // TODO: Rcon
			$plugin = EconomyAPI::getInstance();

			$output = ($plugin->getMessage("topmoney-tag", $this->sender, [
					$this->page,
					$this->max
				]) . "\n");
			$message = ($plugin->getMessage("topmoney-format", $this->sender) . "\n");

			foreach(unserialize($this->topList) as $n => $list) {
				$output .= str_replace([
					"%1",
					"%2",
					"%3"
				], [
					$n,
					$list[0],
					$list[1]
				], $message);
			}
			$output = substr($output, 0, -1);

			if($this->sender === "CONSOLE") {
				$plugin->getLogger()->info($output);
			}else{
				/** @var Player $player */
				$player->sendMessage($output);
			}
		}
	}
}

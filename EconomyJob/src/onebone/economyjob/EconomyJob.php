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

namespace onebone\economyjob;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class EconomyJob extends PluginBase implements Listener {
	/** @var EconomyJob */
	private static $instance;
	/** @var Config */
	private $jobs;
	/** @var Config */
	private $player;
	/** @var  EconomyAPI */
	private $api;

	/**
	 * @return EconomyJob
	 */
	public static function getInstance() {
		return static::$instance;
	}

	public function onEnable() {
		$this->saveResource('jobs.yml');

		$this->jobs = new Config($this->getDataFolder() . "jobs.yml", Config::YAML);
		$this->player = new Config($this->getDataFolder() . "players.yml", Config::YAML);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->api = EconomyAPI::getInstance();
		self::$instance = $this;
	}

	private function readResource($res) {
		$resource = $this->getResource($res);
		if(!is_resource($resource)) {
			$this->getLogger()->debug("Tried to load unknown resource " . TextFormat::AQUA . $res . TextFormat::RESET);
			return false;
		}
		$content = stream_get_contents($resource);
		@fclose($resource);
		return $content;
	}

	public function onDisable() {
		$this->player->save();
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled true
	 * @param BlockBreakEvent $event
	 */
	public function onBlockBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$job = $this->jobs->get($this->player->get($player->getName()));
		if($job !== false) {
			if(isset($job[$block->getID() . ":" . $block->getMeta() . ":break"])) {
				$money = $job[$block->getID() . ":" . $block->getMeta() . ":break"];
				if($money > 0) {
					$this->api->addMoney($player, $money);
				}else{
					$this->api->reduceMoney($player, $money);
				}
			}
		}
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled true
	 * @param BlockPlaceEvent $event
	 */
	public function onBlockPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$job = $this->jobs->get($this->player->get($player->getName()));
		if($job !== false) {
			if(isset($job[$block->getID() . ":" . $block->getMeta() . ":place"])) {
				$money = $job[$block->getID() . ":" . $block->getMeta() . ":place"];
				if($money > 0) {
					$this->api->addMoney($player, $money);
				}else{
					$this->api->reduceMoney($player, $money);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	public function getJobs() {
		return $this->jobs->getAll();
	}

	/**
	 * @return array
	 *
	 */
	public function getPlayers() {
		return $this->player->getAll();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $params): bool {
		switch (array_shift($params)) {
			case "join":
				if(!$sender instanceof Player) {
					$sender->sendMessage("Please run this command in-game.");
					return true;
				}

				if(!$sender->hasPermission('economyjob.command.job.join')) {
					$sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
					return true;
				}

				if($this->player->exists($sender->getName())) {
					$sender->sendMessage("You already have joined job.");
				}else{
					$job = array_shift($params);
					if(trim($job) === "") {
						$sender->sendMessage("Usage: /job join <name>");
						break;
					}
					if($this->jobs->exists($job)) {
						$this->player->set($sender->getName(), $job);
						$sender->sendMessage("You have joined to the job \"$job\"");
					}else{
						$sender->sendMessage("There's no job named \"$job\"");
					}
				}
				break;
			case "retire":
				if(!$sender instanceof Player) {
					$sender->sendMessage("Please run this command in-game.");
					return true;
				}

				if(!$sender->hasPermission('economyjob.command.job.retire')) {
					$sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
					return true;
				}

				if($this->player->exists($sender->getName())) {
					$job = $this->player->get($sender->getName());
					$this->player->remove($sender->getName());
					$sender->sendMessage("You have retired from the job \"$job\"");
				}else{
					$sender->sendMessage("You don't have job that you've joined");
				}
				break;
			case "list":
				if(!$sender->hasPermission('economyjob.command.job.list')) {
					$sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
					return true;
				}

				$jobs = array_keys($this->getJobs());
				$sender->sendMessage(TextFormat::colorize(
					sprintf('List of jobs (&b%d&f): &6%s', count($jobs), implode('&f, &6', $jobs)))
				);
				break;
			case "detail":
				if(!$sender->hasPermission('economyjob.command.job.detail')) {
					$sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
					return true;
				}

				$name = array_shift($params);
				if($name === '') {
					$sender->sendMessage('Usage: /job detail <name>');
					return true;
				}

				$jobs = $this->getJobs();
				if(!isset($jobs[$name])) {
					$sender->sendMessage(sprintf(TextFormat::colorize('There is no job named &6%s&f', $name)));
					return true;
				}

				if(!is_array($jobs[$name])) {
					$sender->sendMessage(sprintf(TextFormat::colorize('Job &6%s&f is not in correct data format.', $name)));
					return true;
				}

				$currency = $this->api->getDefaultCurrency();

				$sender->sendMessage(sprintf(TextFormat::colorize('Job &6%s&f gets:'), $name));
				foreach($jobs[$name] as $key => $money) {
					$condition = explode(':', $key);
					$item = $condition[0] . ':' . $condition[1];
					$action = $condition[2];
					$sender->sendMessage(TextFormat::colorize(sprintf('* &6%s&f if you %s %s.', $currency->format($money),
						($action === 'break' ? '&c':'&a') . $action . '&f', $item)));
				}
				break;
			case "me":
				if(!$sender instanceof Player) {
					$sender->sendMessage("Please run this command in-game.");
					return true;
				}

				if(!$sender->hasPermission('economyjob.command.job.me')) {
					$sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
					return true;
				}

				if($this->player->exists($sender->getName())) {
					$sender->sendMessage("Your job : " . $this->player->get($sender->getName()));
				}else{
					$sender->sendMessage("You don't have any jobs you've joined.");
				}
				break;
			default:
				$sender->sendMessage($command->getUsage());
		}
		return true;
	}
}

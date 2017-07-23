<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
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

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class EconomyJob extends PluginBase implements Listener{
	/** @var Config */
	private $jobs;
	/** @var Config */
	private $player;

	/** @var  EconomyAPI */
	private $api;

	/** @var EconomyJob   */
	private static $instance;

	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(!is_file($this->getDataFolder()."jobs.yml")){
			$this->jobs = new Config($this->getDataFolder()."jobs.yml", Config::YAML, yaml_parse($this->readResource("jobs.yml")));
		}else{
			$this->jobs = new Config($this->getDataFolder()."jobs.yml", Config::YAML);
		}
		$this->player = new Config($this->getDataFolder()."players.yml", Config::YAML);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->api = EconomyAPI::getInstance();
		self::$instance = $this;
	}

	private function readResource($res){
		$path = $this->getFile()."resources/".$res;
		$resource = $this->getResource($res);
		if(!is_resource($resource)){
			$this->getLogger()->debug("Tried to load unknown resource ".TextFormat::AQUA.$res.TextFormat::RESET);
			return false;
		}
		$content = stream_get_contents($resource);
		@fclose($content);
		return $content;
	}

	public function onDisable(){
		$this->player->save();
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled true
	 * @param BlockBreakEvent $event
	 */
	public function onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$job = $this->jobs->get($this->player->get($player->getName()));
		if($job !== false){
			if(isset($job[$block->getID().":".$block->getDamage().":break"])){
				$money = $job[$block->getID().":".$block->getDamage().":break"];
				if($money > 0){
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
	public function onBlockPlace(BlockPlaceEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();

		$job = $this->jobs->get($this->player->get($player->getName()));
		if($job !== false){
			if(isset($job[$block->getID().":".$block->getDamage().":place"])){
				$money = $job[$block->getID().":".$block->getDamage().":place"];
				if($money > 0){
					$this->api->addMoney($player, $money);
				}else{
					$this->api->reduceMoney($player, $money);
				}
			}
		}
	}

	/**
	 * @return EconomyJob
	*/
	public static function getInstance(){
		return static::$instance;
	}

	/**
	 * @return array
	 */
	public function getJobs(){
		return $this->jobs->getAll();
	}

	/**
	 * @return array
	 *
	 */
	public function getPlayers(){
		return $this->player->getAll();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $params): bool{
		switch(array_shift($params)){
			case "join":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
				}
				if($this->player->exists($sender->getName())){
					$sender->sendMessage("You already have joined job.");
				}else{
					$job = array_shift($params);
					if(trim($job) === ""){
						$sender->sendMessage("Usage: /job join <name>");
						break;
					}
					if($this->jobs->exists($job)){
						$this->player->set($sender->getName(), $job);
						$sender->sendMessage("You have joined to the job \"$job\"");
					}else{
						$sender->sendMessage("There's no job named \"$job\"");
					}
				}
				break;
			case "retire":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
				}
				if($this->player->exists($sender->getName())){
					$job = $this->player->get($sender->getName());
					$this->player->remove($sender->getName());
					$sender->sendMessage("You have retired from the job \"$job\"");
				}else{
					$sender->sendMessage("You don't have job that you've joined");
				}
				break;
			case "list":

				$max = 0;
				foreach($this->jobs->getAll() as $d){
					$max += count($d);
				}

				$max = ceil(($max / 5));

				$page = array_shift($params);

				$page = max(1, $page);
				$page = min($max, $page);
				$page = (int)$page;

				$current = 1;
				$n = 1;

				$output = "Showing job list page $page of $max : \n";
				foreach($this->jobs->getAll() as $name => $job){
					$info = "";
					foreach($job as $id => $money){
						$cur = (int)ceil(($n / 5));
					 	if($cur === $page){
							$info .= $name." : ".$id." | ".EconomyAPI::getInstance()->getMonetaryUnit()."".$money."\n";
						}elseif($cur > $page){
							break;
						}
						++$n;
					}
					$output .= $info;
				}
				$sender->sendMessage($output);
				break;
			case "me":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game.");
				}
				if($this->player->exists($sender->getName())){
					$sender->sendMessage("Your job : ".$this->player->get($sender->getName()));
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

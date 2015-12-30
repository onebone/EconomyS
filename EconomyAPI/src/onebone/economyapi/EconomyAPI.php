<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2015  onebone <jyc00410@gmail.com>
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

namespace onebone\economyapi;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Utils;

class EconomyAPI extends PluginBase implements Listener{
	const PACKAGE_VERSION = "5.7";

	private static $instance = null;

	public function onLoad(){
		self::$instance = $this;
	}

	public static function getInstance(){
		return self::$instance;
	}

	public function onEnable(){
		if(!$this->getDataFolder()){
			mkdir($this->getDataFolder());
		}

		$this->saveDefaultConfig();
		$this->initialize();
	}

	private function initialize(){
		if($this->getConfig()->get("check-update")){
			$this->checkUpdate();
		}
		$this->registerCommands();
	}

	private function checkUpdate(){
		try{
			$info = json_decode(Utils::getURL($this->getConfig()->get("update-host")."?version=".$this->getDescription()->getVersion()."&package_version=".self::PACKAGE_VERSION), true);
			if(!isset($info["status"]) or $info["status"] !== true){
				$this->getLogger()->notice("Something went wrong on update server.");
				return false;
			}
			if($info["update-available"] === true){
				$this->getLogger()->notice("Server says new version (".$info["new-version"].") of EconomyS is out. Check it out at ".$info["download-address"]);
			}
			$this->getLogger()->notice($info["notice"]);
			return true;
		}catch(\Throwable $e){
			$this->getLogger()->logException($e);
			return false;
		}
	}

	private function registerCommands(){
		$map = $this->getServer()->getCommandMap();

		$commands = [
			// TODO: Implement commands
		];
		foreach($commands as $cmd => $class){
			$map->register("economyapi", new $class($cmd));
		}
	}
}

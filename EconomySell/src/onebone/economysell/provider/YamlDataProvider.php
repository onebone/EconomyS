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

namespace onebone\economysell\provider;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\utils\Config;

class YamlDataProvider implements DataProvider{
	/** @var Config */
	private $config;

	private $save;

	public function __construct($file, $save){
		$this->config = new Config($file, Config::YAML);

		$this->save = $save;
	}

	public function addSell($x, $y = 0, $z = 0, $level = null, $data = []){
		if($x instanceof Position){
			$data = $y;

			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if($this->config->exists($x.":".$y.":".$z.":".$level)){
			return false;
		}

		$this->config->set($x.":".$y.":".$z.":".$level, $data);
		if($this->save){
			$this->save();
		}
		return true;
	}

	public function getSell($x, $y = 0, $z = 0, $level = null){
		if($x instanceof Position){
			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if(!$this->config->exists($x.":".$y.":".$z.":".$level)){
			return false;
		}
		return $this->config->get($x.":".$y.":".$z.":".$level);
	}

	public function getAll(){
		return $this->config->getAll();
	}

	public function removeSell($x, $y = 0, $z = 0, $level = null){
		if($x instanceof Position){
			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}

		if($this->config->exists($x.":".$y.":".$z.":".$level)){
			$this->config->remove($x.":".$y.":".$z.":".$level);
			return true;
		}
		return false;
	}

	public function save(){
		$this->config->save();
	}

	public function close(){
		$this->save();
	}

	public function getProviderName(){
		return "Yaml";
	}
}

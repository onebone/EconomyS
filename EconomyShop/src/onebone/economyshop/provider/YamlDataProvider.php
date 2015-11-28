<?php

namespace onebone\economyshop\provider;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\utils\Config;

class YamlDataProvider implements DataProvider{
	/** @var Config */
	private $config;

	private $save;

	public function __construct($file, $save){
		$this->config = new Config($file);

		$this->save = $save;
	}

	public function addShop($x, $y = 0, $z = 0, $level = null, $data = []){
		if($x instanceof Position){
			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if($this->config->exists($x.":".$y.":".$z)){
			return false;
		}

		$this->config->set($x.":".$y.":".$z, $data);
		if($this->save){
			$this->save();
		}
		return true;
	}

	public function getShop($x, $y = 0, $z = 0, $level = null){
		if($x instanceof Position){
			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}
		if(!$this->config->exists($x.":".$y.":".$z)){
			return false;
		}
		return $this->config->get($x.":".$y.":".$z);
	}

	public function removeShop($x, $y = 0, $z = 0, $level = null){
		if($x instanceof Position){
			$y = $x->getFloorY();
			$z = $x->getFloorZ();
			$level = $x->getLevel();
			$x = $x->getFloorX();
		}
		if($level instanceof Level){
			$level = $level->getFolderName();
		}

		if($this->config->exists($x.":".$y.":".$z)){
			$this->config->remove($x.":".$y.":".$z);
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
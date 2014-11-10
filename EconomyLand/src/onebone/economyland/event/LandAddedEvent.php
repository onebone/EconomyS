<?php

namespace onebone\economyland\event;

use pocketmine\event\Event;

class LandAddedEvent extends  Event{
	public static $handlerList = null;

	private $id, $startX, $startZ, $endX, $endZ, $level, $price, $player, $expires;

	public function __construct($id, $startX, $startZ, $endX, $endZ, $level, $price, $player, $expires){
		$this->startX = $startX;
		$this->startZ = $startZ;
		$this->endX = $endX;
		$this->endZ = $endZ;
		$this->level = $level;
		$this->price = $price;
		$this->id = $id;
		$this->player = $player;
		$this->expires = $expires;
	}

	public function getId(){
		return $this->id;
	}

	public function getStartX(){
		return $this->startX;
	}

	public function getStartZ(){
		return $this->startZ;
	}

	public function getEndX(){
		return $this->endX;
	}

	public function getEndZ(){
		return $this->endZ;
	}

	public function getLevel(){
		return $this->level;
	}

	public function getPrice(){
		return $this->price;
	}

	public function getExpires(){
		return $this->expires;
	}

	public function getPlayer(){
		return $this->player;
	}
}
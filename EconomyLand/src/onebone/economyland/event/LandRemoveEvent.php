<?php

namespace onebone\economyland\event;

use pocketmine\event\Cancellable;
use pocketmine\event\Event;

class LandRemoveEvent extends Event implements Cancellable{
	public static $handlerList = null;

	private $id;

	public function __construct($id){
		$this->id = $id;
	}

	public function getId(){
		return $this->id;
	}
}
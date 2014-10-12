<?php

namespace onebone\economyland\database;

use pocketmine\Player;

interface Database{
	public function __construct($fileName, $config, $otherName);
	public function getByCoord($x, $z, $level);
	public function getAll();
	public function getLandById($id);
	public function getLandsByOwner($owner);
	public function getLandsByKeyword($keyword);
	public function getInviteeById($id);
	public function addInviteeById($id, $name);
	public function removeInviteeById($id, $name);
	public function addLand($startX, $endX, $startZ, $endZ, $level, $price, $owner, $expires = null,  $invitee = []);
	public function setOwnerById($id, $owner);
	public function removeLandById($id);
	public function canTouch($x, $z, $level, Player $player);
	public function checkOverlap($startX, $endX, $startZ, $endZ, $level);
	public function close();
}
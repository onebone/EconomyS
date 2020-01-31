<?php

namespace onebone\economyland;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\level\Position;
use pocketmine\Player;

class EventListener implements Listener {
	/** @var EconomyLand */
	private $plugin;

	public function __construct(EconomyLand $plugin) {
		$this->plugin = $plugin;
	}

	public function onPlayerMove(PlayerMoveEvent $event) {
		if(!$this->canMove($event->getTo(), $event->getPlayer())) {
			$event->setCancelled();
		}
	}

	public function onPlayerTeleport(EntityTeleportEvent $event) {
		$player = $event->getEntity();
		if(!$player instanceof Player) return;

		if(!$this->canMove($event->getTo(), $player)) {
			$event->setCancelled();
		}
	}

	private function canMove(Position $to, Player $player) {
		$vec = $to->floor();

		$lands = $this->plugin->getLandManager();
		$land = $lands->getLandAt($vec->getX(), $vec->getZ(), $to->getLevel()->getFolderName());
		if($land === null) return true;

		$name = strtolower($player->getName());
		if($land->getOwner() === $name) return true;

		$option = $land->getOption();
		if(!$option->isInvitee($player)) {
			return $option->getAllowIn();
		}

		return true;
	}

	public function onPlayerInteract(PlayerInteractEvent $event) {
		if($event->getAction() === PlayerInteractEvent::LEFT_CLICK_AIR
		or $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR) return;

		$pos = $event->getBlock()->floor();
		$player = $event->getPlayer();

		$lands = $this->plugin->getLandManager();
		$land = $lands->getLandAt($pos->getX(), $pos->getZ(), $player->getLevel()->getFolderName());
		if($land === null) return;

		$name = strtolower($player->getName());
		$owner = $land->getOwner();
		if($owner === $name) return;

		$option = $land->getOption();
		$invitee = $option->getInvitee($player);
		if($invitee === null) {
			if(!$option->getAllowTouch()) {
				$player->sendMessage($this->plugin->getMessage('land-no-permission-touch', [$owner]));
				$event->setCancelled();
			}
		}else{
			if(!$invitee->getAllowTouch()) {
				$player->sendMessage($this->plugin->getMessage('land-no-permission-touch', [$owner]));
				$event->setCancelled();
			}
		}
	}

	public function onPlayerPickup(InventoryPickupItemEvent $event) {
		$inv = $event->getInventory();
		if(!$inv instanceof PlayerInventory) return;

		$player = $inv->getHolder();
		$vec = $event->getItem()->floor();

		$lands = $this->plugin->getLandManager();
		$land = $lands->getLandAt($vec->getX(), $vec->getZ(), $player->getLevel()->getFolderName());
		if($land === null) return;

		$name = strtolower($player->getName());
		if($land->getOwner() === $name) return;

		$option = $land->getOption();
		$invitee = $option->getInvitee($player);
		if($invitee === null) {
			$event->setCancelled(!$option->getAllowPickup());
		}else{
			$event->setCancelled(!$invitee->getAllowPickup());
		}
	}
}

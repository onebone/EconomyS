<?php

namespace onebone\economyland;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
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

	public function onDataPacketSend(DataPacketSendEvent $event) {
		$pk = $event->getPacket();
		if(!$pk instanceof AvailableCommandsPacket) return;
		$player = $event->getPlayer();

		if(!isset($pk->commandData['land'])) return;
		$data = $pk->commandData['land'];

		$data->overloads = [];

		// land pos1, land pos2
		$data->overloads[] = [
			self::also(new CommandParameter(), function($it) {
				/** @var CommandParameter $it */
				$it->paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->paramName = 'pos';
				$it->isOptional = false;
				$it->enum = self::also(new CommandEnum(), function($enum) {
					/** @var CommandEnum $enum */
					$enum->enumName = 'pos1|pos2';
					$enum->enumValues = ['pos1', 'pos2'];
				});
			})
		];

		// land buy
		$data->overloads[] = [
			self::also(new CommandParameter(), function($it) {
				/** @var CommandParameter $it */
				$it->paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->paramName = 'buy';
				$it->isOptional = false;
				$it->enum = self::also(new CommandEnum(), function($enum) {
					/** @var CommandEnum $enum */
					$enum->enumName = 'buy';
					$enum->enumValues = ['buy'];
				});
			})
		];

		// land here
		$data->overloads[] = [
			self::also(new CommandParameter(), function($it) {
				/** @var CommandParameter $it */
				$it->paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->paramName = 'here';
				$it->isOptional = false;
				$it->enum = self::also(new CommandEnum(), function($enum) {
					/** @var CommandEnum $enum */
					$enum->enumName = 'here';
					$enum->enumValues = ['here'];
				});
			})
		];

		// land option
		$data->overloads[] = [
			self::also(new CommandParameter(), function($it) {
				/** @var CommandParameter $it */
				$it->paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->paramName = 'option';
				$it->isOptional = false;
				$it->enum = self::also(new CommandEnum(), function($enum) {
					/** @var CommandEnum $enum */
					$enum->enumName = 'option';
					$enum->enumValues = ['option'];
				});
			}),
			self::also(new CommandParameter(), function($it) use ($player) {
				/** @var CommandParameter $it */
				$it->paramName = 'land ID';
				$it->paramType = AvailableCommandsPacket::ARG_TYPE_STRING;
				$it->isOptional = false;
				$it->enum = self::also(new CommandEnum(), function($enum) use ($player) {
					/** @var CommandEnum $enum */
					$enum->enumName = 'land ID';
					$enum->enumValues = array_map(function($val) use ($player) {
						return $val->getId();
					}, $this->plugin->getLandManager()->getLandsByOwner($player->getName()));
				});
			})
		];

		$pk->commandData['land'] = $data;
	}

	public static function also($object, $callback) {
		$callback($object);
		return $object;
	}
}

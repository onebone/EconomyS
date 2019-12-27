<?php

namespace onebone\economyapi\util;

use pocketmine\Player;

class TransactionAction {
	/** @var int */
	private $type;
	/** @var string */
	private $player;
	/** @var float */
	private $amount;

	/**
	 * TransactionAction constructor.
	 * @param int $type
	 * @param string|Player $player
	 * @param float $amount
	 */
	public function __construct(int $type, $player, float $amount) {
		if($type > 2) {
			throw new \InvalidArgumentException("Invalid transaction type given: $type");
		}

		if($player instanceof Player) {
			$player = $player->getName();
		}

		$this->type = $type;
		$this->player = $player;
		$this->amount = $amount;
	}

	public function getType(): int {
		return $this->type;
	}

	public function getPlayer(): string {
		return $this->player;
	}

	public function getAmount(): float {
		return $this->amount;
	}
}

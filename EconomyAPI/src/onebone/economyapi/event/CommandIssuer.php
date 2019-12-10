<?php

namespace onebone\economyapi\event;

use pocketmine\Player;

class CommandIssuer implements Issuer {
	/** @var Player */
	private $player;
	/** @var string */
	private $command;
	/** @var string */
	private $line;

	public function __construct(Player $player, string $command, string $line) {
		$this->player = $player;
		$this->command = $command;
		$this->line = $line;
	}

	public function getPlayer(): Player {
		return $this->player;
	}

	public function getCommand(): string {
		return $this->command;
	}

	public function getLine(): string {
		return $this->line;
	}

	public function __toString(): string {
		return "Command({$this->line}) from player {$this->player->getName()}";
	}
}

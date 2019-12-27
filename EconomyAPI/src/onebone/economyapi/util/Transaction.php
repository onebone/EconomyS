<?php

namespace onebone\economyapi\util;

class Transaction {
	const ACTION_SET = 0;
	const ACTION_ADD = 1;
	const ACTION_REDUCE = 2;

	/** @var TransactionAction[] */
	private $actions = [];

	/**
	 * @param TransactionAction[] $actions
	 */
	public function __construct(array $actions) {
		$players = [];

		foreach($actions as $action) {
			if(!$action instanceof TransactionAction) {
				throw new \InvalidArgumentException('TransactionAction[] is required to the constructor');
			}

			$username = strtolower($action->getPlayer());
			if(in_array($username, $players)) {
				throw new \InvalidArgumentException('Two or more TransactionAction elements are targeting one player');
			}

			$players[] = $username;
		}

		$this->actions = $actions;
	}

	/**
	 * @return TransactionAction[]
	 */
	public function getActions(): array {
		return $this->actions;
	}
}

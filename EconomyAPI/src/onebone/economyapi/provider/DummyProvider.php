<?php

namespace onebone\economyapi\provider;

class DummyProvider implements Provider {
	public function accountExists($player): bool {
		return false;
	}

	public function createAccount($player, $defaultMoney = 1000): bool {
		return false;
	}

	public function removeAccount($player): bool {
		return false;
	}

	public function getMoney($player) {
		return false;
	}

	public function setMoney($player, $amount): bool {
		return false;
	}

	public function addMoney($player, $amount): bool {
		return false;
	}

	public function reduceMoney($player, $amount): bool {
		return false;
	}

	public function getAll(): array {
		return [];
	}

	public function getName(): string {
		return "Dummy";
	}

	public function save() {

	}

	public function close() {

	}
}

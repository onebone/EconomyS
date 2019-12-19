<?php

namespace onebone\economyapi\provider;

class DummyProvider implements Provider {
	public function accountExists($player) {
		return false;
	}

	public function createAccount($player, $defaultMoney = 1000) {
		return false;
	}

	public function removeAccount($player) {
		return false;
	}

	public function getMoney($player) {
		return false;
	}

	public function setMoney($player, $amount) {
		return false;
	}

	public function addMoney($player, $amount) {
		return false;
	}

	public function reduceMoney($player, $amount) {
		return false;
	}

	public function getAll() {
		return [];
	}

	public function getName() {
		return "Dummy";
	}

	public function save() {

	}

	public function close() {

	}
}

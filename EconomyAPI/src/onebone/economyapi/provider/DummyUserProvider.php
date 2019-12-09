<?php

namespace onebone\economyapi\provider;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\UserInfo;

class DummyUserProvider implements UserProvider {
	public function getName(): string {
		return 'Dummy';
	}

	public function exists(string $username): bool {
		return false;
	}

	public function setLanguage(string $username, string $lang): bool {
		return false;
	}

	public function getLanguage(string $username): string {
		return null;
	}

	public function save() {
	}

	public function close() {
	}

	public function create(string $username): bool {
		return false;
	}

	public function delete(string $username): bool {
		return false;
	}

	public function getUserInfo(string $username): UserInfo {
		return new UserInfo($username, EconomyAPI::getInstance()->getPluginConfig()->getDefaultLanguage());
	}
}

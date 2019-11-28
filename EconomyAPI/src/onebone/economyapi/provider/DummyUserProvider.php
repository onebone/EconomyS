<?php

namespace onebone\economyapi\provider;

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
}

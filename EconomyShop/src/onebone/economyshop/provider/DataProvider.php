<?php

namespace onebone\economyshop\provider;


interface DataProvider{
	/**
	 * @param string $file
	 * @param bool $save
	 */
	public function __construct($file, $save);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param array|int $y
	 * @param int $z
	 * @param \pocketmine\level\Level|string $level
	 * @param array $data
	 *
	 * @return bool
	 */
	public function addShop($x, $y = 0, $z = 0, $level = null, $data = []);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param int $y
	 * @param int $z
	 * @param \pocketmine\level\Level|string $level
	 *
	 * @return mixed
	 */
	public function getShop($x, $y = 0, $z = 0, $level = null);

	/**
	 * @param \pocketmine\level\Position|int $x
	 * @param int $y
	 * @param int $z
	 * @param \pocketmine\level\Level $level
	 *
	 * @return bool
	 */
	public function removeShop($x, $y = 0, $z = 0, $level = null);

	/**
	 * @return string
	 */
	public function getProviderName();

	public function save();
	public function close();
}
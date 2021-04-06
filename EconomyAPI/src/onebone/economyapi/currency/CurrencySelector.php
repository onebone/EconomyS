<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2021  onebone <me@onebone.me>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyapi\currency;

use pocketmine\Player;

/**
 *  Selects currency to do work with when $currency argument at API function
 * calls such as {@see EconomyAPI::myMoney()}, {@see EconomyAPI::setMoney()},
 * {@see EconomyAPI::reduceMoney()}, {@see EconomyAPI::addMoney()}, ...  is
 * not specified. The function calls that omit $currency parameter may be
 * due to the legacy plugins that is created before the EconomyAPI adopted
 * multi currency feature or is just leaving choices to EconomyAPI.
 *  To keep backwards-compatibility, EconomyAPI sets CurrencySelector to
 * select default currency specified at config.yml, which is dollar by default
 * but user may change this behavior using {@see EconomyAPI::setCurrencySelector()}.
 *  Changing this behavior is not encouraged unless you need highly customized
 * behavior on dealing with the currency feature.
 */
interface CurrencySelector {
	/**
	 * Selects Currency instance for the $player to use. Ensure that the
	 * Currency is available to the $player, or the transaction will fail.
	 * Currency availability is decided at {@see Currency::isAvailableTo()}.
	 *
	 * @param Player $player
	 * @return Currency
	 */
	public function getDefaultCurrency(Player $player): Currency;
}

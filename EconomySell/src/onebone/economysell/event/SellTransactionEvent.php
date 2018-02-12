<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>
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

namespace onebone\economysell\event;


use pocketmine\event\Cancellable;
use pocketmine\item\Item;
use pocketmine\event\Event;
use pocketmine\level\Position;
use pocketmine\Player;

class SellTransactionEvent extends Event implements Cancellable{
    public static $handlerList;

    private $player, $position, $item, $price;

    public function __construct(Player $player, Position $position, Item $item, $price){
        $this->player = $player;
        $this->position = $position;
        $this->item = $item;
        $this->price = $price;
    }

    /**
     * @return Player
     */
    public function getPlayer(){
        return $this->player;
    }

    /**
     * @return Position
     */
    public function getPosition(){
        return $this->position;
    }

    /**
     * @return Item
     */
    public function getItem(){
        return $this->item;
    }

    /**
     * @return float
     */
    public function getPrice(){
        return $this->price;
    }
}

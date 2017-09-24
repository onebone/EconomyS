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

namespace onebone\economysell;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;

use onebone\economysell\provider\DataProvider;
use onebone\economysell\provider\YamlDataProvider;
use onebone\economysell\item\ItemDisplayer;
use onebone\economysell\event\SellCreationEvent;
use onebone\economysell\event\SellTransactionEvent;

class EconomySell extends PluginBase implements Listener{
    /**
     * @var DataProvider
     */
    private $provider;

    private $lang;

    private $queue = [], $tap = [], $removeQueue = [], $placeQueue = [];

    /** @var ItemDisplayer[][] */
    private $items = [];

    public function onEnable(){
        $this->saveDefaultConfig();

        if(!$this->selectLang()){
            $this->getLogger()->warning("Invalid language option was given.");
        }

        $provider = $this->getConfig()->get("data-provider");
        switch(strtolower($provider)){
            case "yaml":
                $this->provider = new YamlDataProvider($this->getDataFolder() . "Sells.yml", $this->getConfig()->get("auto-save"));
                break;
            default:
                $this->getLogger()->critical("Invalid data provider was given. EconomySell will be terminated.");
                return;
        }
        $this->getLogger()->notice("Data provider was set to: " . $this->provider->getProviderName());

        $levels = [];
        foreach($this->provider->getAll() as $sell){
            if($sell[9] !== -2){
                if(!isset($levels[$sell[3]])){
                    $levels[$sell[3]] = $this->getServer()->getLevelByName($sell[3]);
                }
                $pos = new Position($sell[0], $sell[1], $sell[2], $levels[$sell[3]]);
                $display = $pos;
                if($sell[9] !== -1){
                    $display = $pos->getSide($sell[9]);
                }
                $this->items[$sell[3]][] = new ItemDisplayer($display, Item::get($sell[4], $sell[5]), $pos);
            }
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $params) : bool{
        switch($command->getName()){
            case "sell":
                switch(strtolower(array_shift($params))){
                    case "create":
                    case "cr":
                    case "c":
                        if(!$sender instanceof Player){
                            $sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
                            return true;
                        }
                        if(!$sender->hasPermission("economysell.command.sell.create")){
                            $sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
                            return true;
                        }
                        if(isset($this->queue[strtolower($sender->getName())])){
                            unset($this->queue[strtolower($sender->getName())]);
                            $sender->sendMessage($this->getMessage("removed-queue"));
                            return true;
                        }
                        $item = array_shift($params);
                        $amount = array_shift($params);
                        $price = array_shift($params);
                        $side = array_shift($params);

                        if(trim($item) === "" or trim($amount) === "" or trim($price) === "" or !is_numeric($amount) or !is_numeric($price)){
                            $sender->sendMessage("Usage: /sell create <item[:damage]> <amount> <price> [side]");
                            return true;
                        }

                        if(trim($side) === ""){
                            $side = Vector3::SIDE_UP;
                        }else{
                            switch(strtolower($side)){
                                case "up":
                                case Vector3::SIDE_UP:
                                    $side = Vector3::SIDE_UP;
                                    break;
                                case "down":
                                case Vector3::SIDE_DOWN:
                                    $side = Vector3::SIDE_DOWN;
                                    break;
                                case "west":
                                case Vector3::SIDE_WEST:
                                    $side = Vector3::SIDE_WEST;
                                    break;
                                case "east":
                                case Vector3::SIDE_EAST:
                                    $side = Vector3::SIDE_EAST;
                                    break;
                                case "north":
                                case Vector3::SIDE_NORTH:
                                    $side = Vector3::SIDE_NORTH;
                                    break;
                                case "south":
                                case Vector3::SIDE_SOUTH:
                                    $side = Vector3::SIDE_SOUTH;
                                    break;
                                case "sell":
                                case -1:
                                    $side = -1;
                                    break;
                                case "none":
                                case -2:
                                    $side = -2;
                                    break;
                                default:
                                    $sender->sendMessage($this->getMessage("invalid-side"));
                                    return true;
                            }
                        }
                        $this->queue[strtolower($sender->getName())] = [
                            $item, (int)$amount, $price, (int)$side
                        ];
                        $sender->sendMessage($this->getMessage("added-queue"));
                        return true;
                    case "remove":
                    case "rm":
                    case "r":
                    case "delete":
                    case "del":
                    case "d":
                        if(!$sender instanceof Player){
                            $sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
                            return true;
                        }
                        if(!$sender->hasPermission("economysell.command.sell.remove")){
                            $sender->sendMessage(TextFormat::RED . "You don't have permission to run this command.");
                            return true;
                        }
                        if(isset($this->removeQueue[strtolower($sender->getName())])){
                            unset($this->removeQueue[strtolower($sender->getName())]);
                            $sender->sendMessage($this->getMessage("removed-rm-queue"));
                            return true;
                        }
                        $this->removeQueue[strtolower($sender->getName())] = true;
                        $sender->sendMessage($this->getMessage("added-rm-queue"));
                        return true;
                    case "list":
                        return true;
                }
        }
        return true;
    }

    public function onPlayerJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();

        if(isset($this->items[$level])){
            foreach($this->items[$level] as $displayer){
                $displayer->spawnTo($player);
            }
        }
    }

    public function onPlayerTeleport(EntityTeleportEvent $event){
        $player = $event->getEntity();
        if($player instanceof Player){
            if(($from = $event->getFrom()->getLevel()) !== ($to = $event->getTo()->getLevel())){
                if($from !== null and isset($this->items[$from->getFolderName()])){
                    foreach($this->items[$from->getFolderName()] as $displayer){
                        $displayer->despawnFrom($player);
                    }
                }
                if($to !== null and isset($this->items[$to->getFolderName()])){
                    foreach($this->items[$to->getFolderName()] as $displayer){
                        $displayer->spawnTo($player);
                    }
                }
            }
        }
    }

    public function onBlockTouch(PlayerInteractEvent $event){
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            return;
        }

        $player = $event->getPlayer();
        $block = $event->getBlock();

        $iusername = strtolower($player->getName());

        if(isset($this->queue[$iusername])){
            $queue = $this->queue[$iusername];
            $item = Item::fromString($queue[0]);
            $item->setCount($queue[1]);

            $ev = new SellCreationEvent($block, $item, $queue[2], $queue[3]);
            $this->getServer()->getPluginManager()->callEvent($ev);

            if($ev->isCancelled()){
                $player->sendMessage($this->getMessage("sell-create-failed"));
                unset($this->queue[$iusername]);
                return;
            }
            $result = $this->provider->addSell($block, [
                $block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getFolderName(),
                $item->getID(), $item->getDamage(), $item->getName(), $queue[1], $queue[2], $queue[3]
            ]);

            if($result){
                if($queue[3] !== -2){
                    $pos = $block;
                    if($queue[3] !== -1){
                        $pos = $block->getSide($queue[3]);
                    }

                    $this->items[$pos->getLevel()->getFolderName()][] = ($dis = new ItemDisplayer($pos, $item, $block));
                    $dis->spawnToAll($pos->getLevel());
                }

                $player->sendMessage($this->getMessage("sell-created"));
            }else{
                $player->sendMessage($this->getMessage("sell-already-exist"));
            }

            if($event->getItem()->canBePlaced()){
                $this->placeQueue[$iusername] = true;
            }

            unset($this->queue[$iusername]);
            return;
        }elseif(isset($this->removeQueue[$iusername])){
            $sell = $this->provider->getSell($block);
            foreach($this->items as $level => $arr){
                foreach($arr as $key => $displayer){
                    $link = $displayer->getLinked();
                    if($link->getX() === $sell[0] and $link->getY() === $sell[1] and $link->getZ() === $sell[2] and $link->getLevel()->getFolderName() === $sell[3]){
                        $displayer->despawnFromAll();
                        unset($this->items[$key]);
                        break 2;
                    }
                }
            }

            $this->provider->removeSell($block);

            unset($this->removeQueue[$iusername]);
            $player->sendMessage($this->getMessage("sell-removed"));

            if($event->getItem()->canBePlaced()){
                $this->placeQueue[$iusername] = true;
            }
            return;
        }

        if(($sell = $this->provider->getSell($block)) !== false){
            if($this->getConfig()->get("enable-double-tap")){
                $now = time();
                if(isset($this->tap[$iusername]) and $now - $this->tap[$iusername] < 1){
                    $this->sellItem($player, $sell);
                    unset($this->tap[$iusername]);
                }else{
                    $this->tap[$iusername] = $now;
                    $player->sendMessage($this->getMessage("tap-again", [$sell[6], $sell[7], $sell[8]]));
                }
            }else{
                $this->sellItem($player, $sell);
            }

            if($event->getItem()->canBePlaced()){
                $this->placeQueue[$iusername] = true;
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event){
        $iusername = strtolower($event->getPlayer()->getName());
        if(isset($this->placeQueue[$iusername])){
            $event->setCancelled();
            unset($this->placeQueue[$iusername]);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $block = $event->getBlock();
        if($this->provider->getSell($block) !== false){
            $player = $event->getPlayer();

            $event->setCancelled(true);
            $player->sendMessage($this->getMessage("sell-breaking-forbidden"));
        }
    }

    private function sellItem(Player $player, $sell){
        if(!$player instanceof Player){
            return false;
        }
        if(!$player->hasPermission("economysell.sell.sell")){
            $player->sendMessage($this->getMessage("no-permission-sell"));
            return false;
        }
        if(is_string($sell[4])){
            $itemId = ItemFactory::fromString($sell[4], false)->getId();
        }else{
            $itemId = ItemFactory::get((int)$sell[4], false)->getId();
        }
        $item = ItemFactory::get($itemId, (int)$sell[5], (int)$sell[7]);
        if($player->getInventory()->contains($item)){
            $ev = new SellTransactionEvent($player, new Position($sell[0], $sell[1], $sell[2], $this->getServer()->getLevelByName($sell[3])), $item, $sell[8]);
            $this->getServer()->getPluginManager()->callEvent($ev);
            if($ev->isCancelled()){
                $player->sendMessage($this->getMessage("failed-sell"));
                return true;
            }
            $player->getInventory()->removeItem($item);
            $player->sendMessage($this->getMessage("sold-item", [$sell[6], $sell[7], $sell[8]]));
            EconomyAPI::getInstance()->addMoney($player, $sell[8]);
        }else{
            $player->sendMessage($this->getMessage("no-item", [$sell[6]]));
        }
        return true;
    }

    public function getMessage($key, $replacement = []){
        $key = strtolower($key);
        if(isset($this->lang[$key])){
            $search = [];
            $replace = [];
            $this->replaceColors($search, $replace);

            $search[] = "%MONETARY_UNIT%";
            $replace[] = EconomyAPI::getInstance()->getMonetaryUnit();

            for($i = 1; $i <= count($replacement); $i++){
                $search[] = "%" . $i;
                $replace[] = $replacement[$i - 1];
            }
            return str_replace($search, $replace, $this->lang[$key]);
        }
        return "Could not find \"$key\".";
    }

    private function selectLang(){
        foreach(preg_grep("/.*lang_.{2}\\.json$/", $this->getResources()) as $resource){
            $lang = substr($resource, -7, -5);
            if($this->getConfig()->get("lang", "en") === $lang){
                $this->lang = json_decode((stream_get_contents($rsc = $this->getResource("lang_" . $lang . ".json"))), true);
                @fclose($rsc);
                return true;
            }
        }
        $this->lang = json_decode((stream_get_contents($rsc = $this->getResource("lang_en.json"))), true);
        @fclose($rsc);
        return false;
    }

    private function replaceColors(&$search = [], &$replace = []){
        $colors = [
            "BLACK" => "0",
            "DARK_BLUE" => "1",
            "DARK_GREEN" => "2",
            "DARK_AQUA" => "3",
            "DARK_RED" => "4",
            "DARK_PURPLE" => "5",
            "GOLD" => "6",
            "GRAY" => "7",
            "DARK_GRAY" => "8",
            "BLUE" => "9",
            "GREEN" => "a",
            "AQUA" => "b",
            "RED" => "c",
            "LIGHT_PURPLE" => "d",
            "YELLOW" => "e",
            "WHITE" => "f",
            "OBFUSCATED" => "k",
            "BOLD" => "l",
            "STRIKETHROUGH" => "m",
            "UNDERLINE" => "n",
            "ITALIC" => "o",
            "RESET" => "r"
        ];
        foreach($colors as $color => $code){
            $search[] = "%%" . $color . "%%";
            $search[] = "&" . $code;

            $replace[] = TextFormat::ESCAPE . $code;
            $replace[] = TextFormat::ESCAPE . $code;
        }
    }

    public function onDisable(){
        if($this->provider instanceof DataProvider){
            $this->provider->close();
        }
    }
}

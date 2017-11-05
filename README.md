## General

[![Poggit Release](https://poggit.pmmp.io/shield.approved/EconomyAPI)](https://poggit.pmmp.io/p/EconomyAPI)

A complete suite of Economy plugins by onebone:

- User oriented plugin
- EconomyAPI Individual language support
- Economy API support
- Direct accessible API
- Lots of configurations
- Lots of events to handle
- Fast processing with massive features

**IMPORTANT: You MUST install EconomyAPI to use ANY of the EconomyS plugins**

## EconomyS

1. EconomyAPI - Main of the €conom¥$ - All plugins below requires this plugin

2. EconomyShop - Buy items

3. EconomySell - Sell items

4. EconomyAirport - Teleport by money

5. EconomyJob - Provide job system into your server

6. EconomyTax - Helps your server balancing money

7. EconomyLand - Helps your server manage lands

8. EconomyPShop - Shops for non-op players - requires ItemCloud plugin

9. EconomyAuction - Open your auction!

10. EconomyCasino - Do casino in your server!

11. EconomyProperty - Buy your land with signs - requires EconomyLand

12. EconomyUsury - Create your usury host and take a loan from host

## EconomyAPI

### Commands

| Default command | Parameter | Description | Default Permission |
| :-----: | :-------: | :---------: | :-------: |
| /mymoney | | Shows your money | All |
| /mydebt | | Shows your debt | All |
| /takedebt | `<money>` | Borrows $`<money>` from plugin | `All` |
| /returndebt | `<money>` | Returns $`<money>` to plugin | `All` |
| /topmoney | `<page>` | Shows server's top money | `All` |
| /moneysave | | Saves data to your hardware | `Console` |
| /moneyload | | Loads data from your hardware | `Console` |
| /setmoney | `<player>` `<money>` | Sets `<player>`'s money to $`<money>` | `OP` `Console` |
| /economys | | Shows plugin which are using EconomyAPI | `All` |
| /givemoney | `<player>` `<money>` | Gives $`<money>` `<player>` | `OP` `Console` |
| /takemoney | `<player>` `<money>` | Takes $`<money>` from `<player>` | `OP` `Console` |
| /seemoney | `<player>` | Shows `<player>`'s money | `All` |
| /bank deposit | `<money>` | Deposit $`<money>` to your account | `All` |
| /bank withdraw | `<money>` | Withdraw $`<money>` from your account | `All` |
| /bank mymoney | | Shows your money from your account | `All` |
| /mystatus | | Shows your money status | `All` |
| /bankadmin takemoney | `<player>` `<money>` | Takes $`<money>` from `<player>`'s account | `OP` `Console` |
| /bankadmin givemoney | `<player>` `<money>` | Gives $`<money>` for `<player>`'s account | `OP` `Console` |


### Configuration

> File : `plugins/EconomyAPI/economy.properties`

| Key | Description | Available Value | Default Value |
| :-: | :---------: | :---------------: | :---------: |
| show-using-economy | Changes server name to `[EconomyS] SERVER NAME`   `on : Change` `off : Don't change` | `on` `off` | on | 
| once-debt-limit | Limits borrowing debt at once | `All integers` | 100 |
| debt-limit | Limits available debt | `All integers` | 500 |
| add-op-at-rank | Shows OP at top money rank    `on : Shows OP` `off : Don't shows OP` | `on` `off` | off |
| default-money | Sets default money | `All integers` | 1000 |
| default-debt | Sets default debt | `All integers` | 0 |
| time-for-increase-debt | Sets how long will take for debt increase | `All integers` | 10 |
| percent-of-increase-debt | Sets percentage of increasing debt | `All integers` | 5 |
| default-bank-money | Sets default bank money | `All integers` | 0 |
| time-for-increase-money | Sets how long will take for credit increase | `All integers` | 10 |
| bank-increase=money-rate | Sets percentage of increasing credit | `All integers` | 5 |
| debug | Money debugging preferences  `on : yes` `off : no` | `on` `off` | on |

## EconomyLand

EconomyLand provides your players a land protection system. If the player buys land, the land will protected for the bought player.

### Commands

List of commands :

`/land <list | here | move | invite | invitee | give | buy | whose>`

`/landsell <here | land number>`

Instructions for /land command :

`/startp` : Sets the start position.

`/endp` : Sets the end position.

`/land list [page]` : Shows the list of land.

`/land here` : Shows the land where you are standing on.

`/land move <land id>` : Move to land.

`/land invite <land id> <invitee>` : Invites player to your land.

`/land invitee <land id>` : Shows the list of invitee in land.

`/land give <land id> <player>` : Give the land to other player.

`/land buy` : Buys land.

`/land whose <keyword>` : Queries the list of land-bought players.

`/landsell here` : Sells land where you're standing on.

`/landsell <land id>` : Sells land by land ID.


If you use `/startp` and `/endp` commands you'll set the position where you'll buy. Then, you can use `/land buy` command to buy the land.

The `land id` is the land ID that shows in `/land list` command.

EconomyLand will ignore Y axis. It will protect all of Y axis which is in area.


### Permissions

```
economyland.*
economyland.land.*
economyland.land.modify.others
economyland.land.modify.whiteland
economyland.land.modify.others
economyland.landsell.*
economyland.landsell.others
economyland.command.*
economyland.command.startp
economyland.command.endp
economyland.command.land.buy
economyland.command.land.move
economyland.command.land.list
economyland.command.land.whose
economyland.command.land.give
economyland.command.land.here
economyland.command.landsell
economyland.command.landsell.here
economyland.command.landsell.number
```

## EconomyShop

### Commands

`/shop create <create|remove|list> [item[:damage]] [amount] [price] [side]` - Tap to activate SHOP signs | `OP` |

Shop are created with `/shop` then tapping any block to activate it.
For example to make a shop selling a Diamond Sword for 500$:

`/shop create 276 1 500` then tap a block/sign.

`Sell Center` in `EconomySell` can be created in same way with `/sell create 276 1 500`. And also `Player's Shop` in `EconomyPShop`


### Configuration

> File : `plugins/EconomyShop/shop.properties`

| Key | Description | Available Value | Default Value |
| :-: | :---------: | :-------------: | :-----------: |
| handler-priority | The priority of handling shop touches | Integer | 5 |


## EconomyPShop

EconomyPShop is the system that lets non-OP players to open their own shop.

**Important: EconomyPShop requires ItemCloud**

First, you have to register and upload your item. Then, you'll create the PShop (Player shop).
Second, other players will tap your pshop sign/block **twice** to buy your item. Then, your item in ItemCloud will be removed.


### Commands

`/pshop create <create|remove|list> [item[:damage]] [amount] [price] [side]` - Tap to activate SHOP signs | `OP` |


## EconomyJob

##### Instructions for jobs.yml configuration

```
tree-cutter:
"17:0:break": 10
```

This is one of the config items in jobs.yml.

'tree-cutter' is name of the job,

In `"17:0:break"` - 17 is item code, 0 is damage of item, break is the method and the 10 on the far right side is the money earned.

So

`"18:0:place": 10`

Means : If I place a block with ID 18 and damage 0 I will earn $10.

##### Instructions for config.yml configuration


`refresh-time:` The time of changing price of item, Integer

`broadcast-refresh:` Whether to broadcast the item has refreshed the price, true/false

`max-change-rate:` The maximum rate of changing money, Integer


##### Permissions

```
economyjob.command.*
economyjob.command.job.join
economyjob.command.job.reture
economyjob.command.job.list
economyjob.command.job.me
```

## EconomyAirport

> File : `%CONFIG_PATH%/DepartureSign.yml`
> File : `%CONFIG_PATH%/ArrivalSign.yml`
> File : `%CONFIG_PATH%/Identifier.yml`

This documentation is focused on default configuration.

| Line1 | Line2 | Line3 | Line4 |
| :---: | :---: | :---: | :---: |
| `<international | airport>` | `<arrival | departure>` | `<arrival:`Airport name` departure:`price`>` | `<arrival:`none` departure:`<target>`>` |

Example:

Line1: airport
Line2: departure
Line3: 10
Line4: onebone

Takes $10 to go to `onebone` airport

Line1: airport
Line2: arrival
Line3: onebone
Line4: 

Arrival sign : `onebone` airport

If there's no target airport to fly, it aborts riding a flight.


## For Developers

You can access to EconomyAPI by using `EconomyAPI::getInstance()`

Example:
```php
EconomyAPI::getInstance()->addMoney($player, $amount);
```

## License
```
EconomyS, the massive economy plugin with many features for PocketMine-MP
Copyright (C) 2013-2017  onebone <jyc00410@gmail.com>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
```

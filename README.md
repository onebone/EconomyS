# EconomyS
The economy plugin for PocketMine-MP.

## Download
[Jenkins](https://jenkins.onebone.me/job/EconomyS/)

## EconomyAPI commands

| Default command | Parameter | Description | Default Permission |
| :-----: | :-------: | :---------: | :-------: |
| /mymoney | | Shows your money | `All` |
| /topmoney | `<page>` | Shows server's top money | `All` |
| /setmoney | `<player>` `<money>` | Sets `<player>`'s money to `<money>` | `OP` `Console` |
| /givemoney | `<player>` `<money>` | Gives `<money>` `<player>` | `OP` `Console` |
| /takemoney | `<player>` `<money>` | Takes `<money>` from `<player>` | `OP` `Console` |
| /seemoney | `<player>` | Shows `<player>`'s money | `All` |
| /mystatus | | Shows your money status | `All` |

## EconomyAPI configuration

> File : `plugins/EconomyAPI/config.yml`

| Key | Description | Available Value | Default Value |
| :----: | :----: | :----: | :----: |
| default-currency | Sets default currency | Currency ID | dollar |
| add-op-at-rank | Option to eliminate OP from top money ranking | bool | false |
| allow-pay-offline | Option to allow players to pay for player who is offline | bool | true |
| default-lang | Default language for players newly joined the server | Language ID* | en |
| auto-save-interval | Interval for auto saving data | number in minutes | 10 |
| send-command-usages |  Option to send command usages | bool | true |
| currencies | Custom currencies | Currency options | |
| check-update | Option to check update on every start up | bool | true |
| update-host | Host address of update check server | string | |
| provider-settings | Option for MySQL database connections | | |

\* Currently available language IDs are: `ch`, `cs`, `de`, `en`, `fr`, `id`, `it`, `ja`, `ko`, `nl`, `ru`, `zh`

## For Developers
You can access to EconomyAPI using `EconomyAPI::getInstance()`

Basic Usage:
```php
EconomyAPI::getInstance()->addMoney($player, $amount);
```

Currency specified:
```php
$api = EconomyAPI::getInstance();
$currency = $api->getDefaultCurrency();
$api->addMoney($player, $amount, $currency);
```

## License
```
EconomyS, the massive economy plugin with many features for PocketMine-MP
Copyright (C) 2013-2021  onebone <me@onebone.me>

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

# EconomyS

## Download
[Jenkins](https://jenkins.onebone.me/job/EconomyS/)

## EconomyAPI commands

| Default command | Parameter | Description | Default Permission |
| :-----: | :-------: | :---------: | :-------: |
| /mymoney | | Shows your money | All |
| /topmoney | `<page>` | Shows server's top money | `All` |
| /setmoney | `<player>` `<money>` | Sets `<player>`'s money to $`<money>` | `OP` `Console` |
| /givemoney | `<player>` `<money>` | Gives $`<money>` `<player>` | `OP` `Console` |
| /takemoney | `<player>` `<money>` | Takes $`<money>` from `<player>` | `OP` `Console` |
| /seemoney | `<player>` | Shows `<player>`'s money | `All` |
| /mystatus | | Shows your money status | `All` |


## EconomyAPI configuration

> File : `plugins/EconomyAPI/economy.properties`

| Key | Description | Available Value | Default Value |
| :-: | :---------: | :---------------: | :---------: |
| add-op-at-rank | Shows OP at top money rank    `on : Shows OP` `off : Don't shows OP` | `true` or `false` | false |
| default-money | Sets default money | `All integers` | 1000 |
| max-money | Limits maximum balance each player may possess | `All integers` | 9999999999 |
| allow-pay-offline | Whether to allow player to pay when target player is offline | `true` or `false` | false |
| default-lang | Sets default language for the plugin | `Available languages` | `def` |
| auto-save-interval | Set interval of auto-save by minutes | `number` | 10 |
| provider | Sets provider for database | `yaml` or `mysql` | `yaml` |
| check-update | Sets whether to check update from server | `true` or `false` | true
| update-host | Sets host where to check update | `Any available URIs` | onebone.me/plugins/economys/api |
| provider-settings | Data which will be given to database provider | `mixed` | mixed |

## For Developers

You can access to EconomyAPI by using `EconomyAPI::getInstance()`

Example:
```php
EconomyAPI::getInstance()->addMoney($player, $amount);
```

## License
```
EconomyS, the massive economy plugin with many features for PocketMine-MP
Copyright (C) 2013-2019  onebone <jyc00410@gmail.com>

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

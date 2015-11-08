<?php

namespace onebone\economyapi\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class SortTask extends AsyncTask{
	private $player, $moneyData, $addOp, $page, $ops, $banList;

	private $max = 0;

	private $topList = null;

	/**
	 * @param string	$player
	 * @param array		$moneyData
	 * @param bool		$addOp
	 * @param int		$page
	 * @param array		$ops
	 * @param array		$banList
	 */
	public function __construct($player, $moneyData, $addOp, $page, $ops, $banList){
		$this->player = $player;
		$this->moneyData = $moneyData;
		$this->addOp = $addOp;
		$this->page = $page;
		$this->ops = $ops;
		$this->banList = $banList;
	}

	public function onRun(){
		$this->topList = json_encode((array)$this->getTopList());
	}

	private function getTopList(){
		$money = $this->moneyData["money"];

		arsort($money);

		$ret = [];

		$n = 1;
		$this->max = ceil((count($money) - count($this->banList) - ($this->addOp ? 0 : count($this->ops))) / 5);
		$this->page = (int)min($this->max, max(1, $this->page));

		foreach($money as $p => $money){
			$p = strtolower($p);

			if(isset($this->banList[$p])) continue;
			if(isset($this->ops[$p]) and $this->addOp === false) continue;

			$current = (int) ceil($n / 5);

			if($current === $this->page){
				$ret[$n] = [$p, $money];
			}elseif($current > $this->page){
				break;
			}
			++$n;
		}
		return $ret;
	}

	public function onCompletion(Server $server){
		if((($player = $this->player) === "CONSOLE") or ($player = $server->getPlayerExact($this->player)) instanceof Player){
			$plugin = EconomyAPI::getInstance();

			$output = "- Showing top money list ({$this->page} of {$this->max}) - \n";
			$message = ($plugin->getMessage("topmoney-format", $this->player, ["%1", "%2", "%3", "%4"])."\n");

			$parseList = (array)json_decode($this->topList, true);
			foreach($this->topList as $n => $list){
				$output .= str_replace(["%1", "%2", "%3"], [$n, $list[0], $list[1]], $message);
			}

			if($player instanceof Player){
				$player->sendMessage($output);
			}else{
				$plugin->getLogger()->info($output);
			}
		}
	}
}

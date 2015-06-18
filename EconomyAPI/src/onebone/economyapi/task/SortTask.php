<?php 

namespace onebone\economyapi\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\Player;

use onebone\economyapi\EconomyAPI;

class SortTask extends AsyncTask{
	private $player, $moneyData, $addOp, $page;
	
	private $max = 0;
	
	/**
	 * @param string	$player
	 * @param array		$moneyData
	 * @param bool		$addOp
	 * @param int		$page
	 */
	public function __construct($player, $moneyData, $addOp, $page){
		$this->player = $player;
		$this->moneyData = $moneyData;
		$this->addOp = $addOp;
		$this->page = $page;
	}
	
	public function onRun(){
		arsort($this->moneyData["money"]);
	}
	
	public function onCompletion(Server $server){
		if((($player = $this->player) === "CONSOLE") or ($player = $server->getPlayerExact($this->player)) instanceof Player){
			$plugin = EconomyAPI::getInstance();
			
			$banList = $server->getNameBans();
			
			$n = 1;
			$max = ceil((count($this->moneyData["money"]) - count($banList->getEntries()) - ($this->addOp ? 0 : count($server->getOPs()->getAll()))) / 5);
			$page = max(1, $this->page);
			$page = min($max, $page);
			$page = (int)$page;
			
			$output = "- Showing top money list ($page of $max) -\n";
			$message = ($plugin->getMessage("topmoney-format", $this->player, array("%1", "%2", "%3", "%4"))."\n");
			
			foreach($this->moneyData["money"] as $player => $money){
				if($banList->isBanned($player)) continue;
				if($server->isOp(strtolower($player)) and ($this->addOp === false)) continue;
				$current = (int)ceil($n / 5);
				if($current === $page){
					$output .= str_replace(array("%1", "%2", "%3"), array($n, $player, $money), $message);
				}elseif($current > $page){
					break;
				}
				++$n;
			}
			if($player instanceof Player){
				$player->sendMessage($output);
			}else{
				$plugin->getLogger()->info($output);
			}
		}
	}
}
<?php
/*
__PocketMine Plugin__
name=EconomyJob
version=1.0.11
author=onebone
apiversion=12,13
class=EconomyJob
*/
/*

=====CHANGE LOG======
V1.0.0: First Release

V1.0.1: More than one item for one job

V1.0.2: More than one type of money

V1.0.3: Lined up alphabet at the list

V1.0.4: Fixed when earning money at spawn point

V1.0.5: Added instructions of jobs.yml

V1.0.6 : Added settings to enable Korean commands

V1.0.7: Now works at DroidPocketMine

V1.0.8:
- Changed jobs.yml adding format changed 
- /job list display formatchanged

V1.0.9 : Compatible with API 11

V1.0.10 : Supports item damage

V1.0.11 : Compatible with API 12 (Amai Beetroot)


*/

class EconomyJob implements Plugin {
	private $api, $job, $player, $jobc, $playerc;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	public function init(){
		if(!isset($this->api->economy) or !$this->api->economy instanceof EconomyAPI){
			console("[ERROR] EconomyAPI not exist");
			$this->api->console->defaultCommands("stop", "", "plugin", false);
			return;
		}
		$this->path = $this->api->plugin->configPath($this);
		file_put_contents($this->path."ReadMe.txt", "EconomyJob instruction:\n\n
   This is the jobs.yml instructions. It is easy when you edited very much. There are instructions below\n\n
	
   tree-cutter:\n
	 id:\n
	  - 17: 4\n
   \n
   This is the default settings of the \"jobs.yml\".\n
   \n
   This is the structure of the job list.\n\n
   (job name):\n
	id:\n
	 - (item id): (money when breaking one of items)\n
	 - (item id: It can be more than two or less than two): (money when breaking one of items)\n\n
	Example:\n
	miner:\n
	  id:\n
		- 1: 2
		- 4: 2
	digger:\n
	  id:\n
		2: 4\n\n
	It must not written by TAB Key.");
		$this->api->plugin->createConfig($this, array("enable-korean" => true));
		$config = $this->api->plugin->readYAML($this->path."config.yml");
		if(!is_file($this->path."jobs.yml")){
			$this->jobc = new Config($this->path."jobs.yml", CONFIG_YAML, array("tree-cutter" => array("id" => array(17 => 4)), "hi"=>array("id"=>array("17:2"=> 4))));
		}else{
			$this->jobc = new Config($this->path."jobs.yml", CONFIG_YAML);
		}
	/*	if(!is_file($this->path."data.dat")){
			file_put_contents($this->path."data.dat", serialize(array()));
		}
		$this->data = unserialize(file_get_contents($this->path."data.dat"));*/
		$this->api->ban->cmdWhitelist("job");
		if($config["enable-korean"]){
			$this->api->ban->cmdWhitelist("직업");
			$this->api->console->register("직업", "<정하기 | 퇴직 | 목록 | 나>", array($this, "cmdHandler"));
		}
		$this->playerc = new Config($this->path."players.yml", CONFIG_YAML, array());
		$this->player = $this->api->plugin->readYAML($this->path."players.yml");
		$this->job = $this->api->plugin->readYAML($this->path."jobs.yml");
		$this->api->addHandler("player.block.break", array($this, "handler"));
		$this->api->console->register("job", "<select | retire | list | me>", array($this, "cmdHandler"));
		$this->api->event("server.close", array($this, "handler"));
		$this->api->addHandler("player.block.break.spawn", array($this, "handler"));
		//$this->api->addHandler("player.block.place.spawn", array($this, "handler"));
		//$this->api->addHandler("player.block.place", array($this, "handler"));
		$this->createConfig();
		$this->api->economy->EconomySRegister("EconomyJob");
	}
	public function __destruct(){}
	public function createConfig(){
		//$this->path = $this->api->plugin->createConfig($this, array("save-blocks" => false));
		//$this->config = $this->api->plugin->readYAML($this->path."config.yml");
	}
	public function handler( &$data, $event){
		switch ($event){
			/*	 case "player.block.place.spawn":
 $spawn = true;
 case "player.block.place":
 if(isset($spawn)) break;
 //   if($this->config["save-blocks"]){
 $this->data[$data["player"]->username][] = array(
 "x" => $data["block"]->x,
 "y" => $data["block"]->y,
 "z" => $data["block"]->z,
 "level" => $data["block"]->level->getName(),
 "item" => $data["item"]->getID(),
 "meta" => $data["item"]->getMetadata(),
 );
 //   }
 break;*/
		case "player.block.break.spawn":
			$spawn = true;
		case "player.block.break":
			if(isset($spawn)){
				break;
			}
			if(isset($this->player[$data["player"]->username]["job"])){
				foreach($this->job as $k => $j){
					if(!is_array($j["id"])){
						continue;
					}
					foreach($j["id"] as $id => $money){
						$item = explode(":", $id);
						if(!isset($item[1]) or $item[1] == "?"){
							$item[1] = "?";
						}
						if($item[0] == $data["target"]->getID() and ($data["target"]->getMetadata() == $item[1] or $item[1] == "?")){
							$this->api->economy->takeMoney($data["player"], $money);
							break;
						}
					}
					/*if(array_key_exists($data["target"]->getID(), $j["id"]) and $this->player[$data["player"]->username]["job"] == $k){
						$this->api->economy->takeMoney($data["player"], $j["id"][$data["target"]->getID()]);
					}*/
				}
			}
			/*	 foreach($this->data as $issuer => $data){
 foreach($data as $key => $value){
 if($value["x"] == $data["target"]->x and $value["y"] == $data["target"]->y and $value["z"] == $data["target"]->z and $value["level"] == $data["target"]->level->getName()){
 unset($this->data[$issuer][$key]);
 break 2;
 }
 }
 }*/
			break;
		case "server.close":
			$this->playerc->setAll($this->player);
			$this->playerc->save();
			//   file_put_contents($this->path."data.dat", serialize($this->data));
			break;
		}
	}
	public function cmdHandler($cmd, $param, $issuer, $alias = false){
		$output = "";
		if(!$issuer instanceof Player){
			return "Your job is console\n";
		}
		switch ($cmd){
		case "job":
		case "직업":
			$lang = $cmd == "job" ? "english" : "korean";
			switch (array_shift($param)){
			case "select":
			case "정하기":
				$job = array_shift($param);
				foreach($this->job as $k => $j){
					if($k == $job){
						$this->player[$issuer->username]["job"] = $job;
						if($lang == "english"){
							$output .= "Your job selected to be a $job\n";
						}else{
							$output .= "당신은 {$job}에 취직하였습니다\n";
						}
						break 2;
					}
				}
				if($lang == "english"){
					$output .= "There's no job named $job\n";
				}else{
					$output .= "{$job}의 이름을 가진 직업이 존재하지 않습니다\n";
				}
				break;
			case "retire":
			case "퇴직":
				if(!isset($this->player[$issuer->username]["job"])){
					if($lang == "english"){
						$output .= "You do not have your job\n";
					}else{
						$output .= "당신은 직업을 가지고 있지 않습니다\n";
					}
				}else{
					unset($this->player[$issuer->username]["job"]);
					if($lang == "english"){
						$output .= "You have been retired from your job.\n";
					}else{
						$output .= "당신은 당신의 직업에서 퇴직하였습니다.\n";
					}
				}
				break;
			case "me":
			case "나":
				if(!isset($this->player[$issuer->username]["job"])){
					if($lang == "english"){
						$output .= "You don't have your job\n";
					}else{
						$output .= "당신은 직업을 가지고 있지 않습니다\n";
					}
				}else{
					if($lang == "english"){
						$output .= "Your job is ".$this->player[$issuer->username]["job"].".\n";
					}else{
						$output .= "당신의 직업은 ".$this->player[$issuer->username]["job"]."입니다.\n";
					}
				}
				break;
			case "list":
			case "목록":
				$page = trim($param[0]) !== "" ? (int) $param[0] : 1;
				$page = (int) max(1, $page);
				$cnt = 0;
				foreach($this->job as $id){
					$cnt += count($id["id"]);
				}
				$max = ceil($cnt / 5);
				$page = min($max, $page);
				if($lang == "english"){
					$output .= "Job list : Showing page $page / $max \n";
				}else{
					$output .= "직업 목록 : 페이지 $page / $max\n";
				}
				$current = 1;
				$proc = 1;
				foreach($this->job as $name => $data){
					foreach($data["id"] as $id => $money){
						$cur = (int) ceil($proc / 5);
						if($current == 6) 
							break;
						if($cur < $page){
							$proc++;
							continue;
						}
						if($cur == $page){
							$i = $id.":0 / $$money";
						}
						$output .= "[$name] $i\n";
						$current++;
					}
				}
				break;
			default:
				if($lang == "english"){
					$output .= "Usage: /job <select | retire | list | me>\n";
				}else{
					$output .= "명령어: /직업 <정하기 | 퇴직 | 목록 | 나>\n";
				}
			}
			break;

		}
		return $output;
	}
}
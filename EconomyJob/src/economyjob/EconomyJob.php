<?php

namespace economyjob;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\TextFormat;

use economyapi\EconomyAPI;

class EconomyJob extends PluginBase implements Listener{
    /** @var Config */
    private $jobs;
    /** @var Config */
    private $player;

	/** @var  EconomyAPI */
	private $api;

    /** @var EconomyJob   */
    private static $instance;

    public function onEnable(){

        @mkdir($this->getDataFolder());
        if(!is_file($this->getDataFolder()."jobs.yml")){
            $this->jobs = new Config($this->getDataFolder()."jobs.yml", Config::YAML, yaml_parse($this->readResource("jobs.yml")));
        }else{
            $this->job = new Config($this->getDataFolder()."jobs.yml", Config::YAML);
        }
        $this->player = new Config($this->getDataFolder()."players.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

		$api = EconomyAPI::getInstance();
        $instance = $this;
    }

	private function readResource($res){
		$path = $this->getFile()."resources/".$res;
		$resource = $this->getResource($res);
		if(!is_resource($resource)){
			$this->getLogger()->debug("Tried to load unknown resource ".TextFormat::AQUA.$res.TextFormat::RESET);
			return false;
		}
		return fread($resource, filesize($path));
	}

    public function onDisable(){
        $this->player->save();
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
		$block = $event->getBlock();
		$job = $this->jobs->get($this->player->get($player->getName()));
		if(isset($job[$block->getID().":".$block->getDamage()])){
			$this->api->addMoney($player, $job[$block->getID().":".$block->getDamage()]);
		}
    }

  /*  public function onBlockPlace(BlockPlaceEvent $event){

	}*/

    /**
     * @return EconomyJob
    */
    public static function getInstance(){
        return static::$instance;
    }

    /**
     * @return Config
     */
    public function getJobs(){
        return $this->jobs;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $params){
        switch(array_shift($params)){
            case "join":
                if($this->player->exists($sender->getName())){
                    $sender->sendMessage("You already have joined job.");
                }else{
                    $job = array_shift($params);
                    if(trim($job) === ""){
                        $sender->sendMessage("Usage: /job join <name>");
                        break;
                    }
                    if($this->jobs->exists($job)){
                        $this->player->set($job);
                        $sender->sendMessage("You have joined to the job \"$job\"");
                    }else{
                        $sender->sendMessage("There's no job named \"$job\"");
                    }
                }
                break;
            case "retire":
                if($this->player->exists($sender->getName())){
                    $this->player->remove($this->player->get($sender->getName()));
                    $sender->sendMessage("You have retired from the job \"$job\"");
                }else{
                    $sender->sendMessage("You don't have job that you've joined");
                }
                break;
            case "list":
                $output = "Showing job list : \n";
                foreach($this->jobs as $name => $job){
                    $info = "";
                    foreach($job as $id => $money){
                        $info .= $id." | $".$money."\n";
                    }
                    $output .= $name." : ".$info."\n";
                }
                $sender->sendMessage($output);
                break;
            case "me":
                if($this->player->exists($sender->getName())){
                    $sender->sendMessage("Your job : ".$this->player->get($sender->getName()));
                }else{
                    $sender->sendMessage("You don't have any jobs you've joined.");
                }
                break;
            default:
                $sender->sendMessage($command->getUsage());
        }
        return true;
    }
}
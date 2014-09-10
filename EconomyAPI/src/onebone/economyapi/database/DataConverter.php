<?php

namespace onebone\economyapi\database;

use pocketmine\utils\Config;

class DataConverter{
	private $moneyData, $debtData, $bankData, $version, $moneyFile, $banFile;
	
	const VERSION_1 = 0x01;
	const VERSION_2 = 0x02;
	
	public function __construct($moneyFile, $bankFile){
		$this->parseData($moneyFile, $bankFile);
	}
	
	private function parseData($moneyFile, $bankFile){
		$moneyCfg = new Config($moneyFile, Config::YAML);
		$bankCfg = new Config($bankFile, Config::YAML);
		$this->moneyFile = $moneyCfg;
		$this->bankFile = $moneyCfg;
		
		if($moneyCfg->exists("version")){
			$this->version = $moneyCfg->get("version");
		}else{
			$this->version = self::VERSION_1;
		}
		
		if($this->version === self::VERSION_1){
			$this->moneyData = $moneyCfg->get("money");
			$this->debtData = $moneyCfg->get("debt");
			$this->bankData =$bankCfg->getAll();
		}else{
			switch($this->version){
				case self::VERSION_2:
				$this->moneyData = array_change_key_case($moneyCfg->get("money"));
				$this->debtData = $moneyCfg->get("debt");
				break;
			}
		}
	}
	
	public function convertData($targetVersion){
		switch($this->version){
			case self::VERSION_1:
			switch($targetVersion){
				case self::VERSION_1:
				return true;
				
				case self::VERSION_2:
				$this->moneyFile->set("version", self::VERSION_2);
				
				$money = array_change_key_case($this->moneyFile->get("money"));
				$debt = array_change_key_case($this->moneyFile->get("debt"));
				$bank = array_change_key_case($this->bankFile->get("money"));
				
				$this->moneyFile->set("money", $money);
				$this->moneyFile->set("debt", $debt);
				$this->bankFile->set("money", $bank);
				
				$this->moneyFile->save();
				$this->bankFile->save();
				return true;
			}
			break;
			case self::VERSION_2:
			
			break;
		}
		return false;
	}
	
	public function getMoneyData(){
		return $this->moneyData;
	}
	
	public function getDebtData(){
		return $this->debtData;
	}
	
	public function getBankData(){
		return $this->bankData;
	}
	
	public function getVersion(){
		return $this->version;
	}
}
<?php

/*
*   Bounty Pocketmine Plugin
*   Copyright (C) 2019-2020 JaxkDev
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
*   Twitter :: @JaxkDev
*   Discord :: JaxkDev#8860
*   Email   :: JaxkDev@gmail.com
*/

/** @noinspection PhpUndefinedMethodInspection */

declare(strict_types=1);
namespace Jack\Bounty;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;


class Main extends PluginBase implements Listener{
    private static $instance;

    public const DATA_VER = 1;
    public const CONFIG_VER = 2;

    public $economy;
    public $dataFile;
    public $configFile;
    public $data;
    public $config;

    public $EventListener;
	
	public function onEnable(){
		self::$instance = $this;
		$this->economy = $this->getServer()->getPluginManager()->getPlugin('EconomyAPI');
		$this->saveResource("config.yml");
		$this->saveResource("help.txt");
		$this->configFile = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		//TODO SQL
		$this->dataFile = new Config($this->getDataFolder() . "data.yml", Config::YAML, ["version" => 1, "bounty" => []]);
		$this->data = $this->dataFile->getAll();
		$this->config = $this->configFile->getAll();
		if(!array_key_exists("version", $this->config) or $this->config["version"] !== $this::CONFIG_VER){
			$this->updateConfig();
			$this->getLogger()->debug("Updated config to version ".$this::CONFIG_VER);
		}
		if(!array_key_exists("version", $this->data) or $this->data["version"] !== $this::DATA_VER){
			$this->updateData();
			$this->getLogger()->debug("Updated data to version ".$this::DATA_VER);
		}
		if(strtolower($this->config["listType"]) !== "blacklist" && strtolower($this->config["listType"]) !== "whitelist"){
			$this->getLogger()->error("Unknown listType '{$this->config["listType"]}' reset to default 'Blacklist'");
			$this->config["listType"] = "Blacklist";
			$this->save(false, true);
		}
		$this->EventListener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($this->EventListener, $this);
		return;
    }
    
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        return $this->EventListener->handleCommand($sender, $cmd, $label, $args);
    }

    public function hasBounty(string $nick) : bool{
        if(isset($this->data['bounty'][strtolower($nick)])){
            return true;
        }
        return false;
    }

    public function getBounty(string $nick) : int{
        if(!isset($this->data['bounty'][strtolower($nick)])){
            return -1;
        }
        return $this->data['bounty'][strtolower($nick)];
    }

    public function save($data = true, $cfg = false){
		if($data === true){
            $this->dataFile->setAll($this->data);
		    $this->dataFile->save();
        }
        if($cfg === true){
            $this->configFile->setAll($this->config);
            $this->configFile->save();
        }
	}

	public function updateData() : void{
	    //This is very unlikely to change more then once or twice a year.

        $this->data["version"] = $this::DATA_VER;

        $this->save(true, false);
    }

	public function updateConfig() : void{
	    //This is going to be very long if not controlled... (todo either support only one version break, or find a new method possibly compare keys and then set default values.)

        $this->config["version"] = $this::CONFIG_VER;
		$this->config["listType"] = "blacklist";
		$this->config["list"] = [];

        $this->save(false, true);
    }
	
    public static function getInstance() : self{
	    return self::$instance;
    }

}

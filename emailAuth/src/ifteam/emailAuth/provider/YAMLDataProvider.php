<?php

/*
 * SimpleAuth plugin for PocketMine-MP
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace ifteam\emailAuth\provider;

use pocketmine\IPlayer;
use pocketmine\utils\Config;
use ifteam\emailAuth\emailAuth;

class YAMLDataProvider implements DataProvider{

	/** @var SimpleAuth */
	protected $plugin;

	public function __construct(emailAuth $plugin){
		$this->plugin = $plugin;
		if(!file_exists($this->plugin->getDataFolder() . "players/")){
			@mkdir($this->plugin->getDataFolder() . "players/");
		}
	}

	public function getPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		if($name === ""){
			return null;
		}
		$path = $this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml";
		if(!file_exists($path)){
			return null;
		}else{
			$config = new Config($path, Config::YAML);
			return $config->getAll();
		}
	}

	public function isPlayerRegistered(IPlayer $player){
		$name = trim(strtolower($player->getName()));

		return file_exists($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}

	public function unregisterPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		@unlink($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}

	public function registerPlayer(IPlayer $player, $hash){
		$name = trim(strtolower($player->getName()));
		@mkdir($this->plugin->getDataFolder() . "players/" . $name{0} . "/");
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->set("registerdate", time());
		$data->set("logindate", time());
		$data->set("lastip", null);
		$data->set("hash", $hash);
		$data->save();

		return $data->getAll();
	}

	public function savePlayer(IPlayer $player, array $config){
		$name = trim(strtolower($player->getName()));
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->setAll($config);
		$data->save();
	}

	public function updatePlayer(IPlayer $player, $lastIP = null, $loginDate = null){
		$data = $this->getPlayer($player);
		if($data !== null){
			if($lastIP !== null){
				$data["lastip"] = $lastIP;
			}
			if($loginDate !== null){
				$data["logindate"] = $loginDate;
			}
			$this->savePlayer($player, $data);
		}
	}

	public function close(){

	}
}
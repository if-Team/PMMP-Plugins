<?php

/*
 * SimpleAuth plugin for PocketMine-MP Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth> This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 */
namespace ifteam\EmailAuth\provider;

use pocketmine\IPlayer;
use pocketmine\utils\Config;
use ifteam\EmailAuth\EmailAuth;

class DummyDataProvider implements DataProvider {
	
	/**
	 * @var EmailAuth
	 */
	protected $plugin;
	public function __construct(EmailAuth $plugin) {
		$this->plugin = $plugin;
	}
	public function getPlayer(IPlayer $player) {
		return null;
	}
	public function isPlayerRegistered(IPlayer $player) {
		return false;
	}
	public function registerPlayer(IPlayer $player, $hash) {
		return null;
	}
	public function unregisterPlayer(IPlayer $player) {
	}
	public function savePlayer(IPlayer $player, array $config) {
	}
	public function updatePlayer(IPlayer $player, $lastIP = null, $loginDate = null) {
	}
	public function close() {
	}
}
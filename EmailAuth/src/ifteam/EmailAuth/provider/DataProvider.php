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

namespace ifteam\EmailAuth\provider;

use pocketmine\IPlayer;
use pocketmine\utils\Config;

interface DataProvider{

	/**
	 * @param IPlayer $player
	 *
	 * @return array, or null if it does not exist
	 */
	public function getPlayer(IPlayer $player);

	/**
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function isPlayerRegistered(IPlayer $player);

	/**
	 * @param IPlayer $player
	 * @param string  $hash
	 *
	 * @return array, or null if error happened
	 */
	public function registerPlayer(IPlayer $player, $hash);

	/**
	 * @param IPlayer $player
	 */
	public function unregisterPlayer(IPlayer $player);

	/**
	 * @param IPlayer $player
	 * @param array   $config
	 */
	public function savePlayer(IPlayer $player, array $config);

	/**
	 * @param IPlayer $player
	 * @param string  $lastId
	 * @param int     $loginDate
	 */
	public function updatePlayer(IPlayer $player, $lastId = null, $loginDate = null);

	public function close();
}

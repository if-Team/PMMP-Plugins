<?php

/*
 * SimpleAuth plugin for PocketMine-MP Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth> This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 */
namespace ifteam\EmailAuth\task;

use pocketmine\scheduler\PluginTask;
use ifteam\EmailAuth\EmailAuth;

class MySQLPingTask extends PluginTask {
	
	/**
	 * @var \mysqli
	 */
	private $database;
	public function __construct(EmailAuth $owner, \mysqli $database) {
		parent::__construct ( $owner );
		$this->database = $database;
	}
	public function onRun($currentTick) {
		$this->database->ping ();
	}
}
?>
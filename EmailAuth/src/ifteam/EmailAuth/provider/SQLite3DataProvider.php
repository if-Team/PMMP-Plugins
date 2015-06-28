<?php

/*
 * SimpleAuth plugin for PocketMine-MP Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth> This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 */
namespace ifteam\EmailAuth\provider;

use pocketmine\IPlayer;
use ifteam\EmailAuth\EmailAuth;

class SQLite3DataProvider implements DataProvider {
	
	/**
	 * @var SimpleAuth
	 */
	protected $plugin;
	
	/**
	 * @var \SQLite3
	 */
	protected $database;
	public function __construct(EmailAuth $plugin) {
		$this->plugin = $plugin;
		if (! file_exists ( $this->plugin->getDataFolder () . "players.db" )) {
			$this->database = new \SQLite3 ( $this->plugin->getDataFolder () . "players.db", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE );
			$resource = $this->plugin->getResource ( "sqlite3.sql" );
			$this->database->exec ( stream_get_contents ( $resource ) );
			fclose ( $resource );
		} else {
			$this->database = new \SQLite3 ( $this->plugin->getDataFolder () . "players.db", SQLITE3_OPEN_READWRITE );
		}
	}
	public function getPlayer(IPlayer $player) {
		$name = trim ( strtolower ( $player->getName () ) );
		
		$prepare = $this->database->prepare ( "SELECT * FROM players WHERE name = :name" );
		$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
		
		$result = $prepare->execute ();
		
		if ($result instanceof \SQLite3Result) {
			$data = $result->fetchArray ( SQLITE3_ASSOC );
			$result->finalize ();
			if (isset ( $data ["name"] ) and $data ["name"] === $name) {
				unset ( $data ["name"] );
				$prepare->close ();
				return $data;
			}
		}
		$prepare->close ();
		
		return null;
	}
	public function isPlayerRegistered(IPlayer $player) {
		return $this->getPlayer ( $player ) !== null;
	}
	public function unregisterPlayer(IPlayer $player) {
		$name = trim ( strtolower ( $player->getName () ) );
		$prepare = $this->database->prepare ( "DELETE FROM players WHERE name = :name" );
		$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
		$prepare->execute ();
	}
	public function registerPlayer(IPlayer $player, $hash) {
		$name = trim ( strtolower ( $player->getName () ) );
		$data = [ 
				"registerdate" => time (),
				"logindate" => time (),
				"lastip" => null,
				"hash" => $hash 
		];
		$prepare = $this->database->prepare ( "INSERT INTO players (name, registerdate, logindate, lastip, hash) VALUES (:name, :registerdate, :logindate, :lastip, :hash)" );
		$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
		$prepare->bindValue ( ":registerdate", $data ["registerdate"], SQLITE3_INTEGER );
		$prepare->bindValue ( ":logindate", $data ["logindate"], SQLITE3_INTEGER );
		$prepare->bindValue ( ":lastip", null, SQLITE3_TEXT );
		$prepare->bindValue ( ":hash", $hash, SQLITE3_TEXT );
		$prepare->execute ();
		
		return $data;
	}
	public function savePlayer(IPlayer $player, array $config) {
		$name = trim ( strtolower ( $player->getName () ) );
		$prepare = $this->database->prepare ( "UPDATE players SET registerdate = :registerdate, logindate = :logindate, lastip = :lastip, hash = :hash WHERE name = :name" );
		$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
		$prepare->bindValue ( ":registerdate", $config ["registerdate"], SQLITE3_INTEGER );
		$prepare->bindValue ( ":logindate", $config ["logindate"], SQLITE3_INTEGER );
		$prepare->bindValue ( ":lastip", $config ["lastip"], SQLITE3_TEXT );
		$prepare->bindValue ( ":hash", $config ["hash"], SQLITE3_TEXT );
		$prepare->execute ();
	}
	public function updatePlayer(IPlayer $player, $lastIP = null, $loginDate = null) {
		$name = trim ( strtolower ( $player->getName () ) );
		if ($lastIP !== null) {
			$prepare = $this->database->prepare ( "UPDATE players SET lastip = :lastip WHERE name = :name" );
			$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
			$prepare->bindValue ( ":lastip", $lastIP, SQLITE3_TEXT );
			$prepare->execute ();
		}
		if ($loginDate !== null) {
			$prepare = $this->database->prepare ( "UPDATE players SET logindate = :logindate WHERE name = :name" );
			$prepare->bindValue ( ":name", $name, SQLITE3_TEXT );
			$prepare->bindValue ( ":logindate", $loginDate, SQLITE3_TEXT );
			$prepare->execute ();
		}
	}
	public function close() {
		$this->database->close ();
	}
}

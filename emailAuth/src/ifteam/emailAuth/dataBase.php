<?php

namespace ifteam\emailAuth;

use pocketmine\utils\Config;
use pocketmine\Player;

class dataBase {
	private $path, $yml;
	public function __construct($path) {
		$this->path = &$path;
		$this->yml = (new Config ( $this->path, Config::YAML, [ "lockDomain" => "naver.com" ] ))->getAll ();
	}
	public function save() {
		$config = new Config ( $this->path, Config::YAML );
		$config->setAll ( $this->yml );
		$config->save ();
	}
	public function getAll() {
		return $this->yml;
	}
	public function checkUserData($email) {
		if (! isset ( $this->yml ["user"] [$email] )) return false;
		if ($this->yml ["lockDomain"] != null) if (explode ( '@', $email )[1] != $this->yml ["lockDomain"]) return false;
		return true;
	}
	public function changeLockDomain($newDomain) {
		$this->yml ["lockDomain"] = $newDomain;
	}
	public function addUser($email, $password, $ip, $set_otp = false, $name) {
		if (! $this->checkUserData ( $email )) return false;
		$this->yml ["user"] [$email] = [ "password" => $password,"ip" => $ip,"isotp" => $set_otp,"name" => $name ];
		$this->yml ["ip"] [$ip] = $email;
		$this->yml ["name"] [$name] = $email;
		return true;
	}
	public function deleteUser($email) {
		if (! $this->checkUserData ( $email )) return false;
		unset ( $this->yml ["ip"] [$this->yml ["user"] [$email] ["ip"]] );
		unset ( $this->yml ["name"] [$this->yml ["user"] [$email] ["name"]] );
		unset ( $this->yml ["user"] [$email] );
		return true;
	}
	public function getUserData($email) {
		if (! $this->checkUserData ( $email )) return false;
		return $this->yml ["user"] [$email];
	}
	public function setUserData($email, Array $data) {
		if (! $this->checkUserData ( $email )) return false;
		if (isset ( $data ["password"] )) $this->yml [$email] ["password"] = $data ["password"];
		if (isset ( $data ["ip"] )) {
			$this->updateIPAddress ( $email, $data ["ip"] );
			$this->yml [$email] ["ip"] = $data ["ip"];
		}
		if (isset ( $data ["name"] )) {
			$this->updateName ( $email, $data ["name"] );
			$this->yml [$email] ["name"] = $data ["name"];
		}
		if (isset ( $data ["isotp"] )) $this->yml [$email] ["isotp"] = $data ["isotp"];
		return true;
	}
	public function getEmail(Player $player) {
		if ($this->getEmailToIp ( $player->getAddress () ) != false) {
			return $this->getEmailToIp ( $player->getAddress () );
		} else if ($this->getEmailToName ( $player->getName () ) != false) {return $this->getEmailToName ( $player->getName () );}
		return false;
	}
	public function getEmailToIp($ip) {
		if (! isset ( $this->yml ["ip"] [$ip] )) return false;
		return $this->yml ["ip"] [$ip];
	}
	public function getEmailToName($name) {
		if (! isset ( $this->yml ["name"] [$name] )) return false;
		return $this->yml ["name"] [$name];
	}
	public function logout($email) {
		if (! $this->checkUserData ( $email )) return false;
		unset ( $this->yml ["ip"] [$this->getUserData ( $email )["ip"]] );
	}
	public function updateIPAddress($email, $ip) {
		if (! $this->checkUserData ( $email )) return false;
		unset ( $this->yml ["ip"] [$this->getUserData ( $email )["ip"]] );
		$this->yml ["ip"] [$ip] = $email;
		return true;
	}
	public function updateName($email, $name) {
		if (! $this->checkUserData ( $email )) return false;
		unset ( $this->yml ["name"] [$this->getUserData ( $email )["name"]] );
		$this->yml ["name"] [$name] = $email;
		return true;
	}
}
?>
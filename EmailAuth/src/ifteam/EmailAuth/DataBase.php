<?php

namespace ifteam\EmailAuth;

use pocketmine\utils\Config;
use pocketmine\Player;

class DataBase {
	private $path, $yml;
	public function __construct($path) {
		$this->path = &$path;
		$this->yml = (new Config ( $this->path, Config::YAML, [ 
				"lockDomain" => "" 
		] ))->getAll ();
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
		if (! isset ( $this->yml ["user"] [$email] ))
			return false;
		if ($this->yml ["lockDomain"] != null) {
			if (explode ( '@', $email )[1] != $this->yml ["lockDomain"]) {
				return false;
			}
		}
		return true;
	}
	public function changeLockDomain($newDomain) {
		$newDomain = strtolower ( $newDomain );
		$this->yml ["lockDomain"] = $newDomain;
	}
	public function getLockDomain() {
		return $this->yml ["lockDomain"];
	}
	public function addAuthReady($name, $hash) {
		$name = strtolower ( $name );
		if ($this->getEmailToName ( $name ) != false) {
			return;
		}
		if (! isset ( $this->yml ["authready"] [$name] ))
			$this->yml ["authready"] [$name] = $hash;
	}
	public function checkAuthReady($name) {
		$name = strtolower ( $name );
		if (isset ( $this->yml ["authready"] [$name] )) {
			return true;
		} else {
			return false;
		}
	}
	public function completeAuthReady($name) {
		$name = strtolower ( $name );
		if (isset ( $this->yml ["authready"] [$name] )) {
			unset ( $this->yml ["authready"] [$name] );
		}
	}
	public function checkAuthReadyKey($name, $password) {
		$name = strtolower ( $name );
		if (isset ( $this->yml ["authready"] [$name] )) {
			if ($this->yml ["authready"] [$name] == $this->hash ( strtolower ( $name ), $password )) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
	public function addUser($email, $password, $ip, $set_otp = false, $name) {
		if ($this->checkUserData ( $email ))
			return false;
		
		$e = explode ( '@', $email );
		if (! isset ( $e [1] )) {
			return false;
		}
		$e1 = explode ( '.', $e [1] );
		if (! isset ( $e1 [1] )) {
			return false;
		}
		$domainLock = $this->getLockDomain ();
		if ($domainLock != null) {
			if ($e [1] != $domainLock) {
				return false;
			}
		}
		
		$this->yml ["user"] [$email] = [ 
				"password" => $this->hash ( strtolower ( $name ), $password ),
				"ip" => $ip,
				"isotp" => $set_otp,
				"name" => $name 
		];
		$this->yml ["ip"] [$ip] = $email;
		$this->yml ["name"] [$name] = $email;
		return true;
	}
	public function deleteUser($email) {
		if (! $this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["ip"] [$this->yml ["user"] [$email] ["ip"]] );
		unset ( $this->yml ["name"] [$this->yml ["user"] [$email] ["name"]] );
		unset ( $this->yml ["user"] [$email] );
		return true;
	}
	public function getUserData($email) {
		if (! $this->checkUserData ( $email ))
			return false;
		return $this->yml ["user"] [$email];
	}
	public function setUserData($email, Array $data) {
		if (! $this->checkUserData ( $email ))
			return false;
		if (isset ( $data ["password"] ))
			$this->yml [$email] ["password"] = $data ["password"];
		if (isset ( $data ["ip"] )) {
			$this->updateIPAddress ( $email, $data ["ip"] );
			$this->yml [$email] ["ip"] = $data ["ip"];
		}
		if (isset ( $data ["name"] )) {
			$this->updateName ( $email, $data ["name"] );
			$this->yml [$email] ["name"] = $data ["name"];
		}
		if (isset ( $data ["isotp"] ))
			$this->yml [$email] ["isotp"] = $data ["isotp"];
		return true;
	}
	public function getEmail(Player $player) {
		if ($this->getEmailToIp ( $player->getAddress () ) != false) {
			return $this->getEmailToIp ( $player->getAddress () );
		} else if ($this->getEmailToName ( $player->getName () ) != false) {
			return $this->getEmailToName ( $player->getName () );
		}
		return false;
	}
	public function getEmailToIp($ip) {
		if (! isset ( $this->yml ["ip"] [$ip] ))
			return false;
		return $this->yml ["ip"] [$ip];
	}
	public function getEmailToName($name) {
		if (! isset ( $this->yml ["name"] [$name] ))
			return false;
		return $this->yml ["name"] [$name];
	}
	public function logout($email) {
		if (! $this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["ip"] [$this->getUserData ( $email )["ip"]] );
	}
	public function updateIPAddress($email, $ip) {
		if (! $this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["ip"] [$this->getUserData ( $email )["ip"]] );
		$this->yml ["ip"] [$ip] = $email;
		return true;
	}
	public function updateName($email, $name) {
		if (! $this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["name"] [$this->getUserData ( $email )["name"]] );
		$this->yml ["name"] [$name] = $email;
		return true;
	}
	/**
	 * Uses SHA-512 [http://en.wikipedia.org/wiki/SHA-2] and Whirlpool [http://en.wikipedia.org/wiki/Whirlpool_(cryptography)]
	 *
	 * Both of them have an output of 512 bits. Even if one of them is broken in the future, you have to break both of them
	 * at the same time due to being hashed separately and then XORed to mix their results equally.
	 *
	 * @param string $salt        	
	 * @param string $password        	
	 *
	 * @return string[128] hex 512-bit hash
	 */
	private function hash($salt, $password) {
		return bin2hex ( hash ( "sha512", $password . $salt, true ) ^ hash ( "whirlpool", $salt . $password, true ) );
	}
}
?>
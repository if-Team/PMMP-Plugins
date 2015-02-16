<?php

namespace emailAuth;

use pocketmine\utils\Config;

class dataBase {
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
		if ($this->yml ["lockDomain"] != null)
			if (explode ( '@', $email, 2 )[1] != $this->yml ["lockDomain"])
				return false;
		return true;
	}
	public function addUser($email, $password, $ip, $set_otp = false) {
		if ($this->checkUserData ( $email ))
			return false;
		$this->yml ["user"] [$email] = [ 
				"password" => $password,
				"ip" => $ip,
				"isotp" => $set_otp 
		];
		$this->yml ["ip"] [$ip] = $email;
		return true;
	}
	public function deleteUser($email) {
		if ($this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["user"] [$email] );
		return true;
	}
	public function getUserData($email) {
		if ($this->checkUserData ( $email ))
			return false;
		return $this->yml ["user"] [$email];
	}
	public function setUserData($email, Array $data) {
		if ($this->checkUserData ( $email ))
			return false;
		if (isset ( $data ["password"] ))
			$this->yml [$email] ["password"] = $data ["password"];
		if (isset ( $data ["ip"] )) {
			$this->updateIPAddress ( $email, $data ["ip"] );
			$this->yml [$email] ["ip"] = $data ["ip"];
		}
		if (isset ( $data ["isotp"] ))
			$this->yml [$email] ["isotp"] = $data ["isotp"];
		return true;
	}
	public function getEmail($ip) {
		if (! isset ( $this->yml ["IP"] [$ip] ))
			return false;
		return $this->yml ["IP"] [$ip];
	}
	public function updateIPAddress($email, $ip) {
		if ($this->checkUserData ( $email ))
			return false;
		unset ( $this->yml ["IP"] [$this->getUserData ( $email )["ip"]] );
		$this->yml ["IP"] [$ip] = $email;
	}
}
?>
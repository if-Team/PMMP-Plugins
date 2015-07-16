<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace ifteam\SimpleArea;

use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\block\Block;

class SimpleArea_Database {
	private $path, $level, $fence_type;
	public $yml, $option, $index, $homelist = [ ];
	public function __construct($path, Level $level, $fence_type = 139) {
		$this->path = &$path;
		$this->level = &$level;
		$this->fence_type = $fence_type;
		$this->yml = (new Config ( $this->path . "protects.yml", Config::YAML, [ "whiteworld" => false,"user-property" => [ ] ] ))->getAll ();
		$this->option = (new Config ( $this->path . "options.yml", Config::YAML, [ "white-allow-option" => [ ],"white-forbid-option" => [ ],"white-pvp-allow" => true,"white-protect" => true,"white-welcome" => "","white-invensave" => false,"enable-setarea" => false ] ))->getAll ();
		$this->index = count ( $this->yml ) - 1;
		$this->allCheckProperty ();
		$this->makeHomeList ();
	}
	public function save() {
		$config = new Config ( $this->path . "protects.yml", Config::YAML );
		$config->setAll ( $this->yml );
		$config->save (true);
		
		$config = new Config ( $this->path . "options.yml", Config::YAML );
		$config->setAll ( $this->option );
		$config->save (true);
	}
	public function getAll() {
		return $this->yml;
	}
	public function getArea($x, $z) {
		foreach ( $this->yml as $area )
			if (isset ( $area ["startX"] )) if ($area ["startX"] <= $x and $area ["endX"] >= $x and $area ["startZ"] <= $z and $area ["endZ"] >= $z) return $area;
		return null;
	}
	public function changeWall($wall) {
		$this->fence_type = $wall;
	}
	public function getAreaById($id) {
		return isset ( $this->yml [$id] ) ? $this->yml [$id] : false;
	}
	public function makeHomeList() {
		foreach ( $this->yml as $area ) {
			if (isset ( $area ["is-home"] ) and $area ["is-home"] == true) {
				if (! isset ( $area ["resident"] [0] )) {
					$this->yml [$area ["ID"]] ["resident"] = [ null ];
				} else {
					if ($area ["resident"] [0] == null) $this->homelist [$area ["ID"]] = null;
				}
			}
		}
	}
	public function getHomeList() {
		return $this->homelist;
	}
	public function addHomeList($id) {
		if (! isset ( $this->homelist [$id] )) $this->homelist [$id] = null;
	}
	public function removeHomeList($id) {
		if (isset ( $this->homelist [$id] )) unset ( $this->homelist [$id] );
	}
	public function getUserHome($username, $number) {
		return isset ( $this->yml ["user-property"] [$username] [$number] ) ? $this->yml ["user-property"] [$username] [$number] : false;
	}
	public function getUserHomes($username) {
		return isset ( $this->yml ["user-property"] [$username] ) ? $this->yml ["user-property"] [$username] : false;
	}
	public function isWhiteWorld() {
		return $this->yml ["whiteworld"];
	}
	public function getWelcome($id) {
		return $this->yml [$id] ["welcome"];
	}
	public function setWhiteWorld($bool) {
		$this->yml ["whiteworld"] = $bool;
	}
	public function getEnableSetArea() {
		return $this->option ["enable-setarea"];
	}
	public function setEnableSetArea($bool) {
		$this->option ["enable-setarea"] = $bool;
	}
	public function getUserProperty($username) {
		return $this->yml ["user-property"] [$username];
	}
	public function addUserProperty($username, $id) {
		if (! isset ( $this->yml ["user-property"] [$username] )) {
			$this->yml ["user-property"] [$username] = [ $id ];
		} else {
			if (! $this->checkUserProperty ( $username, $id )) $this->yml ["user-property"] [$username] [] = $id;
		}
	}
	public function addArea($resident, $startX, $endX, $startZ, $endZ, $ishome = false, $protect = true, $allowOption = [], $forbidOption = [], $rent_allow = true) {
		if ($this->checkOverlap ( $startX, $endX, $startZ, $endZ ) != false) return false;
		
		if ($ishome) {
			if (! isset ( $this->yml ["user-property"] [$resident] )) {
				$this->yml ["user-property"] [$resident] = [ $this->index ];
			} else {
				$this->yml ["user-property"] [$resident] [] = $this->index;
			}
			$this->setFence ( $startX, $endX, $startZ, $endZ, 3 );
		}
		$this->yml [$this->index] = [ "ID" => $this->index,"resident" => [ $resident ],"is-home" => $ishome,"startX" => $startX,"endX" => $endX,"startZ" => $startZ,"endZ" => $endZ,"protect" => $protect,"allow-option" => $allowOption,"forbid-option" => $forbidOption,"rent-allow" => $rent_allow,"welcome" => "","pvp-allow" => true,"invensave" => true ];
		if ($ishome == true and $resident == null) $this->addHomeList ( $this->index );
		return $this->index ++;
	}
	public function setFence($startX, $endX, $startZ, $endZ, $length = 3) {
		$this->setHighestBlockAt ( $startX, $startZ, $this->fence_type );
		for($i = 1; $i <= $length; $i ++) {
			$this->setHighestBlockAt ( $startX + $i, $startZ, $this->fence_type );
			$this->setHighestBlockAt ( $startX, $startZ + $i, $this->fence_type );
		}
		
		$this->setHighestBlockAt ( $startX, $endZ, $this->fence_type );
		for($i = 1; $i <= $length; $i ++) {
			$this->setHighestBlockAt ( $startX + $i, $endZ, $this->fence_type );
			$this->setHighestBlockAt ( $startX, $endZ - $i, $this->fence_type );
		}
		
		$this->setHighestBlockAt ( $endX, $startZ, $this->fence_type );
		for($i = 1; $i <= $length; $i ++) {
			$this->setHighestBlockAt ( $endX - $i, $startZ, $this->fence_type );
			$this->setHighestBlockAt ( $endX, $startZ + $i, $this->fence_type );
		}
		
		$this->setHighestBlockAt ( $endX, $endZ, $this->fence_type );
		for($i = 1; $i <= $length; $i ++) {
			$this->setHighestBlockAt ( $endX - $i, $endZ, $this->fence_type );
			$this->setHighestBlockAt ( $endX, $endZ - $i, $this->fence_type );
		}
	}
	public function removeFence($startX, $endX, $startZ, $endZ, $length = 3) {
		$this->removeHighestWall ( $startX, $startZ );
		for($i = 1; $i <= $length; $i ++) {
			$this->removeHighestWall ( $startX + $i, $startZ );
			$this->removeHighestWall ( $startX, $startZ + $i );
		}
		
		$this->removeHighestWall ( $startX, $endZ );
		for($i = 1; $i <= $length; $i ++) {
			$this->removeHighestWall ( $startX + $i, $endZ );
			$this->removeHighestWall ( $startX, $endZ - $i );
		}
		
		$this->removeHighestWall ( $endX, $startZ );
		for($i = 1; $i <= $length; $i ++) {
			$this->removeHighestWall ( $endX - $i, $startZ );
			$this->removeHighestWall ( $endX, $startZ + $i );
		}
		
		$this->removeHighestWall ( $endX, $endZ );
		for($i = 1; $i <= $length; $i ++) {
			$this->removeHighestWall ( $endX - $i, $endZ );
			$this->removeHighestWall ( $endX, $endZ - $i );
		}
	}
	public function setHighestBlockAt($x, $z, $block) {
		$y = $this->level->getHighestBlockAt ( $x, $z );
		
		if (! $this->isSolid ( $this->level->getBlockIdAt ( $x, $y, $z ) )) $y --;
		
		$this->level->setBlock ( new Vector3 ( $x, ++ $y, $z ), Block::get ( $block ) );
	}
	public function removeHighestWall($x, $z) {
		$y = $this->level->getHighestBlockAt ( $x, $z );
		if ($this->level->getBlockIdAt ( $x, $y, $z ) == $this->fence_type) $this->level->setBlock ( new Vector3 ( $x, $y, $z ), Block::get ( Block::AIR ) );
	}
	public function removeAreaById($id) {
		if (isset ( $this->yml [$id] )) {
			$area = $this->getAreaById ( $id );
			foreach ( $area ["resident"] as $username )
				if (isset ( $this->yml ["user-property"] [$username] )) foreach ( $this->yml ["user-property"] [$username] as $index => $user_area_id )
					if ($user_area_id == $id) unset ( $this->yml ["user-property"] [$username] [$index] );
			
			$area = $this->getAreaById ( $id );
			$this->removeFence ( $area ["startX"], $area ["endX"], $area ["startZ"], $area ["endZ"], 3 );
			$this->removeHomeList ( $id );
			unset ( $this->yml [$id] );
			return true;
		}
		return false;
	}
	public function checkOverlap($startX, $endX, $startZ, $endZ) {
		foreach ( $this->yml as $area ) {
			if (isset ( $area ["startX"] )) if ((($area ["startX"] <= $startX and $area ["endX"] >= $startX) or ($area ["startX"] <= $endX and $area ["endX"] >= $endX)) and (($area ["startZ"] <= $startZ and $area ["endZ"] >= $startZ) or ($area ["endZ"] <= $endZ and $area ["endZ"] >= $endZ))) return $area;
		}
		return false;
	}
	public function checkUserProperty($username, $id = null) {
		if ($id === null) return isset ( $this->yml ["user-property"] [$username] );
		foreach ( $this->yml ["user-property"] [$username] as $target_id )
			if ($target_id == $id) return true;
		return false;
	}
	public function allCheckProperty() {
		foreach ( $this->yml ["user-property"] as $username => $data ) {
			foreach ( $this->yml ["user-property"] [$username] as $index => $target_id ) {
				if ($this->yml [$target_id] ["resident"] [0] != $username) {
					$check = 0;
					foreach ( $this->yml [$target_id] ["resident"] as $check )
						if ($check == $username) $check = 1;
					if ($check == 0) unset ( $this->yml ["user-property"] [$username] [$index] );
				}
			}
		}
	}
	public function isSolid($id) {
		if (isset ( Block::$solid [$id] )) return Block::$solid [$id];
		return true;
	}
	public function isHome($id) {
		return ( bool ) $this->yml [$id] ["is-home"];
	}
	public function isProtected($id) {
		return ( bool ) $this->yml [$id] ["protect"];
	}
	public function isRentAllow($id) {
		return ( bool ) $this->yml [$id] ["rent-allow"];
	}
	public function isAllowOption($id, $option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->yml [$id] ["allow-option"] )) $this->yml [$id] ["allow-option"] = [ ];
		foreach ( $this->yml [$id] ["allow-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ) or ! isset ( $go [1] )) return true;
				if ($io [1] == $go [1]) return true;
			}
		}
		return false;
	}
	public function isForbidOption($id, $option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->yml [$id] ["forbid-option"] )) $this->yml [$id] ["forbid-option"] = [ ];
		foreach ( $this->yml [$id] ["forbid-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ) or ! isset ( $go [1] )) return true;
				if ($io [1] == $go [1]) return true;
			}
		}
		return false;
	}
	public function isWhiteWorldAllowOption($option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->option ["white-allow-option"] )) $this->option ["white-allow-option"] = [ ];
		foreach ( $this->option ["white-allow-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ) or ! isset ( $go [1] )) return true;
				if ($io [1] == $go [1]) return true;
			}
		}
		return false;
	}
	public function isWhiteWorldForbidOption($option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->option ["white-forbid-option"] )) $this->option ["white-forbid-option"] = [ ];
		foreach ( $this->option ["white-forbid-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ) or ! isset ( $go [1] )) return true;
				if ($io [1] == $go [1]) return true;
			}
		}
		return false;
	}
	public function isPvpAllow($id) {
		return ( bool ) $this->yml [$id] ["pvp-allow"];
	}
	public function isWhiteWorldPvpAllow() {
		return ( bool ) $this->option ["pvp-allow"];
	}
	public function isInvenSave($id) {
		if (! isset ( $this->yml [$id] ["invensave"] )) $this->yml [$id] ["invensave"] = true;
		if ($this->yml [$id] ["invensave"]) return true;
		return false;
	}
	public function setInvenSave($id, $bool) {
		if (! isset ( $this->yml [$id] ["invensave"] )) $this->yml [$id] ["invensave"] = true;
		$this->yml [$id] ["invensave"] = $bool;
		return false;
	}
	public function setResident($id, Array $resident) {
		$this->yml [$id] ["resident"] = $resident;
		
		// if ($resident [0] == null) {
		if (empty ( $resident [0] )) {
			$this->addHomeList ( $id );
		} else {
			$this->removeHomeList ( $id );
		}
	}
	public function checkResident($id, $resident) {
		foreach ( $this->yml [$id] ["resident"] as $list )
			if ($list == $resident) return true;
		return false;
	}
	public function setProtected($id, $bool) {
		$this->yml [$id] ["protect"] = ( bool ) $bool;
	}
	public function setAllowOption($id, Array $option) {
		$this->yml [$id] ["allow-option"] = $option;
	}
	public function setForbidOption($id, Array $option) {
		$this->yml [$id] ["forbid-option"] = $option;
	}
	public function setRentAllow($id, $bool) {
		$this->yml [$id] ["rent-allow"] = $bool;
	}
	public function setWelcome($id, $text) {
		$this->yml [$id] ["welcome"] = $text;
	}
	public function setPvpAllow($id, $bool) {
		$this->yml [$id] ["pvp-allow"] = $bool;
	}
	public function addAllowOption($id, $option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->yml [$id] ["allow-option"] )) $this->yml [$id] ["allow-option"] = [ ];
		foreach ( $this->yml [$id] ["allow-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] )) return false;
				if ($io [1] == $go [1]) return false;
			}
		}
		$this->yml [$id] ["allow-option"] [] = $option;
		return true;
	}
	public function addForbidOption($id, $option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->yml [$id] ["forbid-option"] )) $this->yml [$id] ["forbid-option"] = [ ];
		foreach ( $this->yml [$id] ["forbid-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] )) return false;
				if ($io [1] == $go [1]) return false;
			}
		}
		$this->yml [$id] ["forbid-option"] [] = $option;
		return true;
	}
	public function addWhiteWorldAllowOption($option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->option ["white-allow-option"] )) $this->option ["white-allow-option"] = [ ];
		foreach ( $this->option ["white-allow-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] )) return false;
				if ($io [1] == $go [1]) return false;
			}
		}
		$this->option ["white-allow-option"] [] = $option;
		return true;
	}
	public function addWhiteWorldForbidOption($option) {
		$io = explode ( ":", $option );
		if (! isset ( $this->option ["white-forbid-option"] )) $this->option ["white-forbid-option"] = [ ];
		foreach ( $this->option ["white-forbid-option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] )) return false;
				if ($io [1] == $go [1]) return false;
			}
		}
		$this->option ["white-forbid-option"] [] = $option;
		return true;
	}
	public function addResident($id, $resident) {
		$this->yml [$id] ["resident"] [] = $resident;
		$this->removeHomeList ( $id );
	}
	public function removeUserProperty($username, $id) {
		if (isset ( $this->yml ["user-property"] [$username] )) foreach ( $this->yml ["user-property"] [$username] as $index => $target_id )
			if ($target_id == $id) unset ( $this->yml ["user-property"] [$username] [$index] );
	}
	public function removeResident($id, $resident) {
		foreach ( $this->yml [$id] ["resident"] as $index => $target )
			if ($target == $resident) unset ( $this->yml [$id] ["resident"] [$index] );
		if ($this->yml [$id] ["resident"] [0] == null) $this->addHomeList ( $id );
	}
}

?>

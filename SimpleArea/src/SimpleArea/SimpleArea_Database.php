<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace SimpleArea;

use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\block\Block;

class SimpleArea_Database {
	private $path, $level, $fence_type;
	private $yml, $index, $homelist = [ ];
	public function __construct($path, Level $level, $fence_type = 139) {
		$this->path = &$path;
		$this->level = &$level;
		$this->fence_type = $fence_type;
		$this->yml = (new Config ( $this->path . "protects.yml", Config::YAML, [ 
				"whiteworld" => false,
				"user-property" => [ ] ] ))->getAll ();
		$this->option = (new Config ( $this->path . "options.yml", Config::YAML, [ 
				"white-option" => [ ],
				"white-pvp-allow" => true,
				"white-welcome" => "",
				"hometax-enable" => false,
				"hometax-price" => "2500" ] ))->getAll ();
		$this->makeHomeList ();
		$this->index = count ( $this->yml ) - 1;
	}
	public function save() {
		$config = new Config ( $this->path . "protects.yml", Config::YAML );
		$config->setAll ( $this->yml );
		$config->save ();
		
		$config = new Config ( $this->path . "options.yml", Config::YAML );
		$config->setAll ( $this->option );
		$config->save ();
	}
	public function getAll() {
		return $this->yml;
	}
	public function getArea($x, $z) {
		foreach ( $this->yml as $area )
			if (isset ( $area ["startX"] )) if ($area ["startX"] <= $x and $area ["endX"] >= $x and $area ["startZ"] <= $z and $area ["endZ"] >= $z) return $area;
		return false;
	}
	// TODO homeTax 매일마다 혹은 재부팅시마다 작동하게?..
	public function homeTaxCheck() {
		foreach ( $this->yml as $area )
			if (isset ( $area ["startX"] )) if (! isset ( $area ["tax-time"] )) $area ["tax-time"] = date ( "Y-m-d H:i:s" );
		// TODO 토지세 내게하기
	}
	public function homeTaxClear() {
		foreach ( $this->yml as $area )
			if (isset ( $area ["startX"] )) if (isset ( $area ["tax-time"] )) unset ( $this->yml [$area ["ID"] ["tax-time"]] );
	}
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
	public function hometaxEnable() {
		if ($this->option ["hometax-enable"]) {
			$this->option ["hometax-enable"] = false;
			return false;
		} else {
			$this->option ["hometax-enable"] = true;
			return true;
		}
	}
	public function hometaxPrice($price) {
		$this->option ["hometax-price"] = $price;
	}
	public function changeWall($wall) {
		$this->fence_type = $wall;
	}
	public function getAreaById($id) {
		return isset ( $this->yml [$id] ) ? $this->yml [$id] : false;
	}
	public function makeHomeList() {
		foreach ( $this->yml as $area )
			if (isset ( $area ["is-home"] ) and $area ["is-home"] == true and $area ["resident"] == null) $this->homelist [$area ["ID"]] = null;
	}
	public function getHomeList($id) {
		return $this->homelist;
	}
	public function addHomeList($id) {
		$this->homelist [$id] = null;
	}
	public function removeHomeList($id) {
		unset ( $this->homelist [$id] );
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
	public function getUserProperty($username) {
		return $this->yml ["user-property"] [$username];
	}
	public function addUserProperty($username, $id) {
		if (! isset ( $this->yml ["user-property"] [$username] )) {
			$this->yml ["user-property"] [$username] = [ 
					$id ];
		} else {
			if (! $this->checkUserProperty ( $username, $id )) $this->yml ["user-property"] [$username] [] = $id;
		}
	}
	public function addArea($resident, $startX, $endX, $startZ, $endZ, $ishome = false, $protect = true, $option = [], $rent_allow = true) {
		if ($this->checkOverlap ( $startX, $endX, $startZ, $endZ ) != false) return false;
		
		if ($ishome) {
			if (! isset ( $this->yml ["user-property"] [$resident] )) {
				$this->yml ["user-property"] [$resident] = [ 
						$this->index ];
			} else {
				$this->yml ["user-property"] [$resident] [] = $this->index;
			}
			$this->setFence ( $startX, $endX, $startZ, $endZ, 3 );
		}
		$this->yml [$this->index] = [ 
				"ID" => $this->index,
				"resident" => [ 
						$resident ],
				"is-home" => $ishome,
				"startX" => $startX,
				"endX" => $endX,
				"startZ" => $startZ,
				"endZ" => $endZ,
				"protect" => $protect,
				"option" => $option,
				"rent-allow" => $rent_allow,
				"welcome" => "",
				"pvp-allow" => true ];
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
	public function setHighestBlockAt($x, $z, $block) {
		$y = $this->level->getHighestBlockAt ( $x, $z );
		
		if (! $this->isSolid ( $this->level->getBlockIdAt ( $x, $y, $z ) )) $y --;
		
		$this->level->setBlock ( new Vector3 ( $x, ++ $y, $z ), Block::get ( $block ) );
	}
	public function removeAreaById($id) {
		if (isset ( $this->yml [$id] )) {
			$area = $this->getAreaById ( $id );
			foreach ( $area ["resident"] as $username )
				foreach ( $this->yml ["user-property"] [$username] as $index => $user_area_id )
					if ($user_area_id == $id) unset ( $this->yml ["user-property"] [$username] [$index] );
			
			unset ( $this->yml [$id] );
			return true;
		}
		return false;
	}
	public function checkOverlap($startX, $endX, $startZ, $endZ) {
		foreach ( $this->yml as $area ) {
			if (isset ( $area ["startX"] )) if ((($area ["startX"] < $startX and $area ["endX"] > $startX) or ($area ["startX"] < $endX and $area ["endX"] > $endX)) and (($area ["startZ"] < $startZ and $area ["endZ"] > $startZ) or ($area ["endZ"] < $endZ and $area ["endZ"] > $endZ))) return $area;
		}
		return false;
	}
	public function checkUserProperty($username, $id = null) {
		if ($id === null) return isset ( $this->yml ["user-property"] [$username] );
		foreach ( $this->yml ["user-property"] [$username] as $target_id )
			if ($target_id == $id) return true;
		return false;
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
	public function isOption($id, $option) {
		$io = explode ( ":", $option );
		foreach ( $this->yml [$id] ["option"] as $getoption ) {
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
	public function setResident($id, Array $resident) {
		$this->yml [$id] ["resident"] = $resident;
	}
	public function checkResident($id, $resident) {
		foreach ( $this->yml [$id] ["resident"] as $list )
			if ($list == $resident) return true;
		return false;
	}
	public function setProtected($id, $bool) {
		$this->yml [$id] ["protect"] = ( bool ) $bool;
	}
	public function setOption($id, Array $option) {
		$this->yml [$id] ["option"] = $option;
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
	public function addOption($id, $option) {
		$io = explode ( ":", $option );
		foreach ( $this->yml [$id] ["option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] )) return false;
				if ($io [1] == $go [1]) return false;
			}
		}
		$this->yml [$id] ["option"] [] = $option;
		return true;
	}
	public function addResident($id, $resident) {
		$this->yml [$id] ["resident"] [] = $resident;
	}
	public function removeUserProperty($username, $id) {
		foreach ( $this->yml ["user-property"] [$username] as $index => $target_id )
			if ($target_id == $id) unset ( $this->yml ["user-property"] [$username] [$index] );
	}
	public function removeResident($id, $resident) {
		foreach ( $this->yml [$id] ["resident"] as $index => $target )
			if ($target == $resident) unset ( $this->yml [$id] ["resident"] [$index] );
	}
}

?>

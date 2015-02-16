<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\PSYCHOPASS\database;

use pocketmine\utils\Config;

class AreaPASS_Database {
	private $path, $level;
	private $yml, $index;
	public function __construct($path, $level) {
		$this->path = $path;
		$this->level = $level;
		$this->yml = (new Config ( $path . $level . ".yml", Config::YAML, [ 
				"whiteworld" => false,
				"name" => [ ] 
		] ))->getAll ();
		$this->index = count ( $this->yml );
	}
	public function getAll() {
		return $this->yml;
	}
	public function getArea($x, $z) {
		foreach ( $this->yml as $area ) {
			if ($area ["startX"] < $x and $area ["endX"] > $x and $area ["startZ"] < $z and $area ["endZ"] > $z)
				return $area;
		}
		return false;
	}
	public function isWhiteWorld() {
		return $this->yml ["whiteworld"];
	}
	public function isProtected($id) {
		return $this->yml [$id] ["protect"];
	}
	public function isOption($id, $option) {
		$io = explode ( ":", $option );
		foreach ( $this->yml [$id] ["option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ))
					return true;
				if ($io [1] == $go [1])
					return true;
			}
		}
		return false;
	}
	public function getOption($id) {
		return $this->yml [$id] ["option"];
	}
	public function setWhiteWorld($bool) {
		$this->yml ["whiteworld"] = $bool;
	}
	public function setProtected($id, $bool) {
		$this->yml [$id] ["protect"] = $bool;
	}
	public function setOption($id, Array $option) {
		$this->yml [$id] ["option"] = $option;
	}
	public function addOption($id, $option) {
		$io = explode ( ":", $option );
		foreach ( $this->yml [$id] ["option"] as $getoption ) {
			$go = explode ( ":", $getoption );
			if ($io [0] == $go [0]) {
				if (! isset ( $io [1] ))
					return false;
				if ($io [1] == $go [1])
					return false;
			}
		}
		$this->yml [$id] ["option"] [] = $option;
		return true;
	}
	public function getAreaByName($name) {
		return isset ( $this->yml ["name"] [$name] ) ? $this->getAreaById ( $this->yml ["name"] [$name] ) : false;
	}
	public function getAreaById($id) {
		return isset ( $this->yml [$id] ) ? $this->yml [$id] : false;
	}
	public function addArea($name, $startX, $endX, $startZ, $endZ, $protect = true, $option = []) {
		if ($this->checkOverlap ( $startX, $endX, $startZ, $endZ ) != false)
			return false;
		$this->yml ["name"] = [ 
				$name => $this->index 
		];
		$this->yml [$this->index] = [ 
				"ID" => $this->index,
				"name" => $name,
				"startX" => $startX,
				"endX" => $endX,
				"startZ" => $startZ,
				"endZ" => $endZ,
				"protect" => $protect,
				"option" => $option 
		];
		return $this->index ++;
	}
	public function removeAreaById($id) {
		if (isset ( $this->yml [$id] )) {
			unset ( $this->yml [$id] );
			return true;
		}
		return false;
	}
	public function checkOverlap($startX, $endX, $startZ, $endZ) {
		foreach ( $this->yml as $area ) {
			if ((($area ["startX"] < $startX and $area ["endX"] > $startX) or ($area ["startX"] < $endX and $area ["endX"] > $endX)) and (($area ["startZ"] < $startZ and $area ["endZ"] > $startZ) or ($area ["endZ"] < $endZ and $area ["endZ"] > $endZ)))
				return $area;
		}
		return false;
	}
	public function close() {
		$config = new Config ( $this->path, Config::YAML );
		$config->setAll ( $this->yml );
		$config->save ();
	}
}

?>
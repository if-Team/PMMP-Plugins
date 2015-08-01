<?php
	
namespace codeinside\simpleelevator;

use codeinside\simpleelevator\SimpleElevator

use pocketmine\math\Vector3;
use pocketmine\block\Block;



//class - FloorManager

//getElevatorColor
//getElevatorBaseBlock
//getCurrentFloorIndex
//getFirstFloorIndex
//getLastFloorIndex
//getNextFloorBlock
//gerPrevFloorBlock
//getFloorBlockByIndex

class SimpleElevator {
	
	public function getColor($type) {
		switch($type) {
			
			case self::TYPE_IRON:
				return self::COLOR_IRON;
				break;
				
			case self::TYPE_GOLD:
				return self::COLOR_GOLD;
				break;
				
			case self::TYPE_DIAMOND:
				return self::COLOR_DIAMOND;
				break;
				
			case self::TYPE_EMERALD:
				return self::COLOR_EMERALD;
				break;
				
			default:
				return "";
		}
	}
	
	private function moveFloor($player, $type, $currentPos, $targetPos) {
		$this->log("moveFloor - " . $currentPos->getY() . "->" . $targetPos->getY());
		
		if(isset($this->elevateQueue[$player->getName()])) {
			return;
		}
		
		$blocks = [];
		$range = $targetPos->getY() - $currentPos->getY();
		
		//clearBlockOnTop
		if($range > 0) {
			for($e = 0; $e <= $range; $e++) {
				
				$id = $player->getLevel()->getBlockIdAt($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ());
				
				if($id === self::TYPE_GLASS || $id === self::TYPE_IRON || $id === self::TYPE_GOLD || $id === self::TYPE_DIAMOND || $id === self::TYPE_EMERALD) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $id
					];
					
					$player->getLevel()->setBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()), Block::get(0, 0), true, false);
				}
			}
		//clearBlockOnBottom
		}else {
			for($e = 0; $e >= $range; $e--) {
				
				$id = $player->getLevel()->getBlockIdAt($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ());
				
				if($id === self::TYPE_GLASS || $id === self::TYPE_IRON || $id === self::TYPE_GOLD || $id === self::TYPE_DIAMOND || $id === self::TYPE_EMERALD) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $id
					];
					
					$player->getLevel()->setBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()), Block::get(0, 0), true, false);
				}
			}
		}
		$this->elevateQueue[$player->getName()] = [
			self::TICK_MODE => self::MODE_MOVE,
			self::TICK_TYPE => $type,
			self::TICK_START_POS => $currentPos,
			self::TICK_END_POS => $targetPos,
			self::TICK_PLAYER => $player,
			self::TICK_FALL_TIME => 20,
			self::TICK_BLOCKS => $blocks
		];
	}
	
	//this method will be EXCLUSION
	public function hasNextFloor($level, $pos) {
		$this->log("hasNextFloor");
		for($e = 1; $e <= 128; $e++) {
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() + $e, $pos->getZ());
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				return true;
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	//this method will be EXCLUSION
	public function hasPrevFloor($level, $pos) {
		$this->log("hasPrevFloor");
		for($e = 1; $e <= 128; $e++) {
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() - $e, $pos->getZ());
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				return true;
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getFloorIndex($level, $pos) {
		$this->log("getFloorIndex -");
		
		$floor = 0;
		
		for($e = $pos->getY(); $e >= 0; $e--) {
			
			$id = $level->getBlockIdAt($pos->getX(), $e, $pos->getZ());
			
			if($id === Block::GLASS) {
				
				$floor++;
				
			}else if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $floor;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		$floor = 0;
		
		for($e = $pos->getY(); $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $e, $pos->getZ());
			
			if($id === Block::GLASS) {
				
				$floor--;
				
			}else if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $floor;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		$this->getServer()->getLogger()->alert("<function 'getFloor'> can't find current floor at Level:" . $level->getName() . " X:" . $pos->getX() . " Y:" . $pos->getY() . " Z:" . $pos->getZ());
		return 0;
	}
	
	public function getBaseFloor($level, $pos) {
		$this->log("getBaseFloor - ");
		
		for($e = 0; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		for($e = 0; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		return false;
	}
	
	public function getFloorByIndex($level, $pos, $index) {
		$this->log("getFloorByIndex - " . $index);
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		if($index == 0) {
			
			return $base;
			
		}else if($index > 0) {
			
			$floor = 0;
			
			for($e = 1; $e <= 128; $e++) {
				
				$block = $level->getBlock(new Vector3($base->getX(), $base->getY() + $e, $base->getZ()));
				$id = $block->getId();
				
				if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
					
					if(++$floor == $index) {
						return $block;
					}
					
				}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
					break;
				}
			}
			return false;
		}else {
			
			$floor = 0;
			
			for($e = 1; $e <= 128; $e++) {
				
				$block = $level->getBlock(new Vector3($base->getX(), $base->getY() - $e, $base->getZ()));
				$id = $block->getId();
				
				if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
					
					if(--$floor == $index) {
						return $block;
					}
					
				}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
					break;
				}
			}
			return false;
		}
	}
	
	public function getNextFloor($level, $pos) {
		$this->log("getNextFloor -");
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getPrevFloor($level, $pos) {
		$this->log("getPrevFloor -");
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getFirstFloorIndex($level, $pos) {
		$this->log("getFirstFloorIndex -");
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		$pos = $base;
		$floor = 0;
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				$floor--;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	public function getLastFloorIndex($level, $pos) {
		$this->log("getLastFloorIndex -");
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		$pos = $base;
		$floor = 0;
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				$floor++;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	public function getBaseBlockId($level, $pos) {
		$this->log("getBaseBlockId -");
		
		for($e = 0; $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() - $e, $pos->getZ());
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $id;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		for($e = 0; $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() + $e, $pos->getZ());
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $id;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		return -1;
	}
}
?>
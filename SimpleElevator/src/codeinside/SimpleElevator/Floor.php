<?php
	
namespace codeinside\simpleelevator;

use codeinside\simpleelevator\SimpleElevator;

use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Level;



//class - Floor

//getColor
//getCurrentFloorIndex
//getFirstFloorIndex
//getLastFloorIndex
//getBaseFloorBlock
//getNextFloorBlock
//gerPrevFloorBlock
//getFloorBlockByIndex

class Floor {
	
	private $x, $y, $z, $vector, $level, $block, $id, $data;
	private $height;
	
	public function __construct(Level $level, Vector3 $pos){
		if(!($pos instanceof Vector3 && $level instanceof Level)) {
			echo "this is not Floor!\n";
		}
		$this->vector = $pos;
		$this->x = $pos->getX();
		$this->y = $pos->getY();
		$this->z = $pos->getZ();
		$this->level = $level;
		$this->block = $level->getBlock($pos);
		$this->id = $this->block->getId();
		$this->data = $this->block->getDamage();
		$this->height = 128;//$this->level->getHeightMap($this->x, $this->z);
		echo "max height: $this->height\n";
		if(!($this->isElevatorBlock($this->block) || $this->isExtensionFloorBlock($this->block))) {
			echo "THIS IS NOT FLOOR!\n";
		}
	}
	
	
	
	public static function isBlockEqual($block1, $block2) {
		return $block1->getId() === $block2->getId() && $block1->getDamage() === $block2->getDamage();
	}
	
	
	
	public static function isElevatorBlock($block) {
		if(self::isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-SLOW")) || self::isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-FAST")) || self::isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-SLOW")) || self::isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-FAST"))) {
			return true;
		}else {
			return false;
		}
	}
	
	
	
	public static function isExtensionFloorBlock($block) {
		return self::isBlockEqual($block, SimpleElevator::getBlock("TYPE-EXTENSION-FLOOR"));
	}
	
	
	
	public static function isExceptionBlock($block) {
		$id = $block->getId();
		switch($id) {
			case Block::AIR:
			case Block::WALL_SIGN:
			case Block::SIGN_POST:
			case Block::TORCH:
			case Block::FIRE:
			case Block::SNOW_LAYER:
			case Block::VINE:
				return false;
			default:
				return true;
		}
	}
	
	
	
	public function getLevel() {
		return $this->level;
	}
	
	
	
	public function getPosition() {
		return new Vector3($this->x, $this->y, $this->z);
	}
	
	
	
	public function getUpButton() {
		$button = $this->level->getTile(new Vector3($this->x, $this->y + 2, $this->z));
		if($button instanceof Sign) {
			return $button;
		}
		return false;
	}
	
	
	
	public function getDownButton() {
		$button = $this->level->getTile(new Vector3($this->x, $this->y + 1, $this->z));
		if($button instanceof Sign) {
			return $button;
		}
		return false;
	}
	
	
	
	public function getType() {
		$block = $this->block;
		
		if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-SLOW"))) {
			return 0;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-FAST"))) {
			return 1;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-SLOW"))) {
			return 2;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-FAST"))) {
			return 3;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-EXTENSION-FLOOR"))) {
			return 4;
		}else {
			return -1;
		}
	}
	
	
	
	public function getBaseType() {
		$block = $this->getBaseFloorBlock();
		
		if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-SLOW"))) {
			return 0;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-FAST"))) {
			return 1;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-SLOW"))) {
			return 2;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-FAST"))) {
			return 3;
		}else {
			return -1;
		}
	}
	
	
	
	public function getColor() {
		$block = $this->getBaseFloorBlock();
		
		if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-SLOW"))) {
			return SimpleElevator::COLOR_IRON;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-SIMPLE-FAST"))) {
			return SimpleElevator::COLOR_GOLD;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-SLOW"))) {
			return SimpleElevator::COLOR_EMERALD;
		}else if($this->isBlockEqual($block, SimpleElevator::getBlock("TYPE-ADVANCE-FAST"))) {
			return SimpleElevator::COLOR_DIAMOND;
		}else {
			return "";
		}
	}
	
	
	
	public function getCurrentFloorIndex() {
		$floor = 0;
		
		for($y = $this->y; $y >= 0; $y--) {
			$block = $this->level->getBlock(new Vector3($this->x, $y, $this->z));
			
			if($this->isExtensionFloorBlock($block)) {
				$floor++;
			}else if($this->isElevatorBlock($block)) {
				return $floor;
			}else if($this->isExceptionBlock($block)) {
				break;
			}
		}
		
		$floor = 0;
		
		for($y = $this->y; $y < $this->height; $y++) {
			$block = $this->level->getBlock(new Vector3($this->x, $y, $this->z));
			
			if($this->isExtensionFloorBlock($block)) {
				$floor--;
			}else if($this->isElevatorBlock($block)) {
				return $floor;
			}else if($this->isExceptionBlock($block)) {
				break;
			}
		}
		
		return null;
	}
	
	
	
	public function getFirstFloorIndex() {
		$base = $this->getBaseFloorBlock();
		
		if($base === false) {
			return false;
		}
		
		$floor = 0;
		
		for($y = $base->getY() - 1; $y >= 0; $y--) {
			$block = $this->level->getBlock(new Vector3($base->getX(), $y, $base->getZ()));
			
			if($this->isExtensionFloorBlock($block)) {
				$floor--;
			}else if($this->isExceptionBlock($block)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	
	
	public function getLastFloorIndex() {
		$base = $this->getBaseFloorBlock();
		
		if($base === false) {
			return false;
		}
		
		$floor = 0;
		
		for($y = $base->getY() + 1; $y < $this->height; $y++) {
			$block = $this->level->getBlock(new Vector3($base->getX(), $y, $base->getZ()));
			
			if($this->isExtensionFloorBlock($block)) {
				$floor++;
			}else if($this->isExceptionBlock($block)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	
	
	public function getBaseFloorBlock() {
		for($y = $this->y; $y >= 0; $y--) {
			$block = $this->level->getBlock(new Vector3($this->x, $y, $this->z));
			
			if($this->isElevatorBlock($block)) {
				return $block;
			}else if($this->isExceptionBlock($block) && !$this->isExtensionFloorBlock($block)) {
				break;
			}
		}
		
		for($y = $this->y; $y < $this->height; $y++) {
			$block = $this->level->getBlock(new Vector3($this->x, $y, $this->z));
			
			if($this->isElevatorBlock($block)) {
				return $block;
			}else if($this->isExceptionBlock($block) && !$this->isExtensionFloorBlock($block)) {
				break;
			}
		}
		
		return false;
	}
	
	
	
	public function getNextFloorBlock() {
		for($y = $this->y + 1; $y < $this->height; $y++) {
			$block = $this->level->getBlock(new Vector3($this->x	, $y, $this->z));
			
			if($this->isExtensionFloorBlock($block) || $this->isElevatorBlock($block)) {
				return $block;
			}else if($this->isExceptionBlock($block)) {
				break;
			}
		}
		
		return false;
	}
	
	
	
	public function getPrevFloorBlock() {
		for($y = $this->y - 1; $y >= 0; $y--) {
			$block = $this->level->getBlock(new Vector3($this->x, $y, $this->z));
			
			if($this->isExtensionFloorBlock($block) || $this->isElevatorBlock($block)) {
				return $block;
			}else if($this->isExceptionBlock($block)) {
				break;
			}
		}
		
		return false;
	}
	
	
	
	public function getFloorBlockByIndex($index) {
		$base = $this->getBaseFloorBlock();
		
		if($base === false) {
			return false;
		}
		
		if($index === 0) {
			return $base;
		}else if($index > 0) {
			$floor = 0;
			
			for($y = $base->getY() + 1; $y < $this->height; $y++) {
				$block = $this->level->getBlock(new Vector3($base->getX(), $y, $base->getZ()));
				
				if($this->isExtensionFloorBlock($block)) {
					if(++$floor == $index) {
						return $block;
					}
				}else if($this->isExceptionBlock($block)) {
					break;
				}
			}
		}else if($index < 0) {
			$floor = 0;
			
			for($y = $base->getY() - 1; $y >= 0; $y--) {
				$block = $level->getBlock(new Vector3($base->getX(), $y, $base->getZ()));
				
				if($this->isExtensionFloorBlock($block)) {
					if(--$floor == $index) {
						return $block;
					}
				}else if($this->isExceptionBlock($block)) {
					break;
				}
			}
		}
		
		return false;
	}
}
?>
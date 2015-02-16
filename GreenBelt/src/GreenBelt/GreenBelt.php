<?php

namespace GreenBelt;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\block\Block;
use pocketmine\utils\Random;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\level\ChunkManager;
use pocketmine\block\Sapling;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;

class GreenBelt extends PluginBase implements Listener {
	var $type = 1;
	public $overridable = [ 
			0 =>\true,
			2 =>\true,
			3 =>\true,
			6 =>\true,
			17 =>\true,
			18 =>\true 
	];
	public $SpruceTree_totalHeight = 8;
	public $SpruceTree_leavesBottomY = - 1;
	public $SpruceTree_leavesMaxRadius = - 1;
	public $PineTree_totalHeight = 8;
	public $PineTree_leavesSizeY = - 1;
	public $PineTree_leavesAbsoluteMaxRadius = - 1;
	public $SmallTree_trunkHeight = 5;
	public static $SmallTree_leavesHeight = 4; // All trees appear to be 4 tall
	public static $SmallTree_leafRadii = [ 
			1,
			1.41,
			2.83,
			2.24 
	];
	public $SmallTree_addLeavesVines = \false;
	public $SmallTree_addLogVines = \false;
	public $SmallTree_addCocoaPlants = \false;
	public $pk;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->pk = new UpdateBlockPacket ();
	}
	public function onSapling(BlockUpdateEvent $event) {
		if ($event->isCancelled ())
			return;
		if ($event->getBlock ()->getId () == Block::SAPLING) {
			$this->growTree ( $event->getBlock ()->getLevel (), $event->getBlock ()->x, $event->getBlock ()->y, $event->getBlock ()->z, new Random (\mt_rand () ), $event->getBlock ()->getDamage () & 0x07 );
		}
	}
	public function growTree(ChunkManager $level, $x, $y, $z, Random $random, $type = 0) {
		switch ($type & 0x03) {
			case Sapling::SPRUCE :
				$this->type = 1;
				if ($random->nextRange ( 0, 1 ) === 1) {
					if ($this->SpruceTree_canPlaceObject ( $level, $x, $y, $z, $random )) {
						$this->SpruceTree_placeObject ( $level, $x, $y, $z, $random );
						
						$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
								$this,
								"UpdateBlockToUsingChunk" 
						], [ 
								$level,
								$x,
								$y,
								$z,
								Block::TRUNK,
								$this->type 
						] ), 1 );
					}
				} else {
					if ($this->PineTree_canPlaceObject ( $level, $x, $y, $z, $random )) {
						$this->PineTree_placeObject ( $level, $x, $y, $z, $random );
						
						$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
								$this,
								"UpdateBlockToUsingChunk" 
						], [ 
								$level,
								$x,
								$y,
								$z,
								Block::TRUNK,
								$this->type 
						] ), 1 );
					}
				}
				break;
			case Sapling::BIRCH :
				$tree = new SmallTree ();
				$this->type = Sapling::BIRCH;
				if ($this->SmallTree_canPlaceObject ( $level, $x, $y, $z, $random )) {
					$this->SmallTree_placeObject ( $level, $x, $y, $z, $random );
					
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"UpdateBlockToUsingChunk" 
					], [ 
							$level,
							$x,
							$y,
							$z,
							Block::TRUNK,
							$this->type 
					] ), 1 );
				}
				break;
			case Sapling::JUNGLE :
				$this->type = Sapling::JUNGLE;
				if ($this->SmallTree_canPlaceObject ( $level, $x, $y, $z, $random )) {
					$this->SmallTree_placeObject ( $level, $x, $y, $z, $random );
					
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"UpdateBlockToUsingChunk" 
					], [ 
							$level,
							$x,
							$y,
							$z,
							Block::TRUNK,
							$this->type 
					] ), 1 );
				}
				break;
			case Sapling::OAK :
			default :
				if ($this->SmallTree_canPlaceObject ( $level, $x, $y, $z, $random )) {
					$this->SmallTree_placeObject ( $level, $x, $y, $z, $random );
					// $this->UpdateBlockToUsingChunk ( $level, $x, $y, $z, Block::TRUNK, $this->type );
					
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"UpdateBlockToUsingChunk" 
					], [ 
							$level,
							$x,
							$y,
							$z,
							Block::TRUNK,
							$this->type 
					] ), 1 );
				}
				break;
		}
	}
	public function UpdateBlockToUsingChunk(ChunkManager $level, $x, $y, $z, $id, $meta = 0) {
		foreach ( $level->getUsingChunk ( $x >> 4, $z >> 4 ) as $player ) {
			$this->pk->x = $x;
			$this->pk->y = $y;
			$this->pk->z = $z;
			$this->pk->block = $id;
			$this->pk->meta = $meta;
			$player->directDataPacket ( $this->pk );
		}
	}
	public function SpruceTree_canPlaceObject(ChunkManager $level, $x, $y, $z, Random $random) {
		$this->SpruceTree_findRandomLeavesSize ( $random );
		$checkRadius = 0;
		for($yy = 0; $yy < $this->SpruceTree_totalHeight + 2; ++ $yy) {
			if ($yy === $this->SpruceTree_leavesBottomY) {
				$checkRadius = $this->SpruceTree_leavesMaxRadius;
			}
			for($xx = - $checkRadius; $xx < ($checkRadius + 1); ++ $xx) {
				for($zz = - $checkRadius; $zz < ($checkRadius + 1); ++ $zz) {
					if (! isset ( $this->overridable [$level->getBlockIdAt ( $x + $xx, $y + $yy, $z + $zz )] )) {
						return\false;
					}
				}
			}
		}
		
		return\true;
	}
	public function SpruceTree_findRandomLeavesSize(Random $random) {
		$this->SpruceTree_totalHeight += $random->nextRange ( - 1, 2 );
		$this->SpruceTree_leavesBottomY = ( int ) ($this->SpruceTree_totalHeight - $random->nextRange ( 1, 2 ) - 3);
		$this->SpruceTree_leavesMaxRadius = 1 + $random->nextRange ( 0, 1 );
	}
	public function SpruceTree_placeObject(ChunkManager $level, $x, $y, $z, Random $random) {
		if ($this->SpruceTree_leavesBottomY === - 1 or $this->SpruceTree_leavesMaxRadius === - 1) {
			$this->SpruceTree_findRandomLeavesSize ( $random );
		}
		$level->setBlockIdAt ( $x, $y - 1, $z, Block::DIRT );
		$this->UpdateBlockToUsingChunk ( $level, $x, $y - 1, $z, Block::DIRT );
		$leavesRadius = 0;
		for($yy = $this->SpruceTree_totalHeight; $yy >= $this->SpruceTree_leavesBottomY; -- $yy) {
			for($xx = - $leavesRadius; $xx <= $leavesRadius; ++ $xx) {
				for($zz = - $leavesRadius; $zz <= $leavesRadius; ++ $zz) {
					if (\abs ( $xx ) != $leavesRadius or \abs ( $zz ) != $leavesRadius or $leavesRadius <= 0) {
						$level->setBlockIdAt ( $x + $xx, $y + $yy, $z + $zz, Block::LEAVES );
						$level->setBlockDataAt ( $x + $xx, $y + $yy, $z + $zz, $this->type );
						$this->UpdateBlockToUsingChunk ( $level, $x + $xx, $y + $yy, $z + $zz, Block::LEAVES, $this->type );
					}
				}
			}
			if ($leavesRadius > 0 and $yy === ($y + $this->SpruceTree_leavesBottomY + 1)) {
				-- $leavesRadius;
			} elseif ($leavesRadius < $this->SpruceTree_leavesMaxRadius) {
				++ $leavesRadius;
			}
		}
		for($yy = 0; $yy < ($this->SpruceTree_totalHeight - 1); ++ $yy) {
			$level->setBlockIdAt ( $x, $y + $yy, $z, Block::TRUNK );
			$level->setBlockDataAt ( $x, $y + $yy, $z, $this->type );
			$this->UpdateBlockToUsingChunk ( $level, $x, $y + $yy, $z, Block::TRUNK, $this->type );
		}
	}
	public function PineTree_canPlaceObject(ChunkManager $level, $x, $y, $z, Random $random) {
		$this->PineTree_findRandomLeavesSize ( $random );
		$checkRadius = 0;
		for($yy = 0; $yy < $this->PineTree_totalHeight; ++ $yy) {
			if ($yy === $this->PineTree_leavesSizeY) {
				$checkRadius = $this->PineTree_leavesAbsoluteMaxRadius;
			}
			for($xx = - $checkRadius; $xx < ($checkRadius + 1); ++ $xx) {
				for($zz = - $checkRadius; $zz < ($checkRadius + 1); ++ $zz) {
					if (! isset ( $this->overridable [$level->getBlockIdAt ( $x + $xx, $y + $yy, $z + $zz )] )) {
						return \false;
					}
				}
			}
		}
		
		return \true;
	}
	public function PineTree_findRandomLeavesSize(Random $random) {
		$this->PineTree_totalHeight += $random->nextRange ( - 1, 2 );
		$this->PineTree_leavesSizeY = 1 + $random->nextRange ( 0, 2 );
		$this->PineTree_leavesAbsoluteMaxRadius = 2 + $random->nextRange ( 0, 1 );
	}
	public function PineTree_placeObject(ChunkManager $level, $x, $y, $z, Random $random) {
		if ($this->PineTree_leavesSizeY === - 1 or $this->PineTree_leavesAbsoluteMaxRadius === - 1) {
			$this->PineTree_findRandomLeavesSize ( $random );
		}
		$level->setBlockIdAt ( $x, $y - 1, $z, Block::DIRT );
		$this->UpdateBlockToUsingChunk ( $level, $x, $y - 1, $z, Block::DIRT );
		$leavesRadius = 0;
		$leavesMaxRadius = 1;
		$leavesBottomY = $this->PineTree_totalHeight - $this->PineTree_leavesSizeY;
		$firstMaxedRadius = \false;
		for($leavesY = 0; $leavesY <= $leavesBottomY; ++ $leavesY) {
			$yy = $this->PineTree_totalHeight - $leavesY;
			for($xx = - $leavesRadius; $xx <= $leavesRadius; ++ $xx) {
				for($zz = - $leavesRadius; $zz <= $leavesRadius; ++ $zz) {
					if (\abs ( $xx ) != $leavesRadius or \abs ( $zz ) != $leavesRadius or $leavesRadius <= 0) {
						$level->setBlockIdAt ( $x + $xx, $y + $yy, $z + $zz, Block::LEAVES );
						$level->setBlockDataAt ( $x + $xx, $y + $yy, $z + $zz, $this->type );
						$this->UpdateBlockToUsingChunk ( $level, $x + $xx, $y + $yy, $z + $zz, Block::LEAVES, $this->type );
					}
				}
			}
			if ($leavesRadius >= $leavesMaxRadius) {
				$leavesRadius = $firstMaxedRadius ? 1 : 0;
				$firstMaxedRadius = \true;
				if (++ $leavesMaxRadius > $this->PineTree_leavesAbsoluteMaxRadius) {
					$leavesMaxRadius = $this->PineTree_leavesAbsoluteMaxRadius;
				}
			} else {
				++ $leavesRadius;
			}
		}
		$trunkHeightReducer = $random->nextRange ( 0, 3 );
		for($yy = 0; $yy < ($this->PineTree_totalHeight - $trunkHeightReducer); ++ $yy) {
			$level->setBlockIdAt ( $x, $y + $yy, $z, Block::TRUNK );
			$level->setBlockDataAt ( $x, $y + $yy, $z, $this->type );
		}
	}
	public function SmallTree_canPlaceObject(ChunkManager $level, $x, $y, $z, Random $random) {
		$radiusToCheck = 0;
		for($yy = 0; $yy < $this->SmallTree_trunkHeight + 3; ++ $yy) {
			if ($yy == 1 or $yy === $this->SmallTree_trunkHeight) {
				++ $radiusToCheck;
			}
			for($xx = - $radiusToCheck; $xx < ($radiusToCheck + 1); ++ $xx) {
				for($zz = - $radiusToCheck; $zz < ($radiusToCheck + 1); ++ $zz) {
					if (! isset ( $this->overridable [$level->getBlockIdAt ( $x + $xx, $y + $yy, $z + $zz )] )) {
						return\false;
					}
				}
			}
		}
		
		return\true;
	}
	public function SmallTree_placeObject(ChunkManager $level, $x, $y, $z, Random $random) {
		// The base dirt block
		$level->setBlockIdAt ( $x, $y, $z, Block::DIRT );
		$this->UpdateBlockToUsingChunk ( $level, $x, $y, $z, Block::DIRT );
		
		// Adjust the tree trunk's height randomly
		// plot [-14:11] int( x / 8 ) + 5
		// - min=4 (all leaves are 4 tall, some trunk must show)
		// - max=6 (top leaves are within ground-level whacking range
		// on all small trees)
		$heightPre = $random->nextRange ( - 14, 11 );
		$this->SmallTree_trunkHeight =\intval ( $heightPre / 8 ) + 5;
		
		// Adjust the starting leaf density using the trunk height as a
		// starting position (tall trees with skimpy leaves don't look
		// too good)
		$leafPre = $random->nextRange ( $this->SmallTree_trunkHeight, 10 ) / 20; // (TODO: seed may apply)
		                                                                         
		// Now build the tree (from the top down)
		$leaflevel = 0;
		for($yy = ($this->SmallTree_trunkHeight + 1); $yy >= 0; -- $yy) {
			if ($leaflevel < self::$SmallTree_leavesHeight) {
				// The size is a slight variation on the trunkheight
				$radius = self::$SmallTree_leafRadii [$leaflevel] + $leafPre;
				$bRadius = 3;
				for($xx = - $bRadius; $xx <= $bRadius; ++ $xx) {
					for($zz = - $bRadius; $zz <= $bRadius; ++ $zz) {
						if(\sqrt($xx ** 2 + $zz ** 2) <= $radius){
							$level->setBlockIdAt ( $x + $xx, $y + $yy, $z + $zz, Block::LEAVES );
							$level->setBlockDataAt ( $x + $xx, $y + $yy, $z + $zz, $this->type );
							$this->UpdateBlockToUsingChunk ( $level, $x + $xx, $y + $yy, $z + $zz, Block::LEAVES, $this->type );
						}
					}
				}
				$leaflevel ++;
			}
			
			// Place the trunk last
			if ($leaflevel > 1) {
				$level->setBlockIdAt ( $x, $y + $yy, $z, Block::TRUNK );
				$level->setBlockDataAt ( $x, $y + $yy, $z, $this->type );
				$this->UpdateBlockToUsingChunk ( $level, $x, $y + $yy, $z, Block::TRUNK, $this->type );
			}
		}
	}
}

?>
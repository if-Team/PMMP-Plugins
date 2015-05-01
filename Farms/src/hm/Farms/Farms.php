<?php

namespace hm\Farms;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Flowable;
use pocketmine\level\Level;
use pocketmine\Player;

class Farms extends PluginBase implements Listener {
	public $farmlist, $farmdata;
	public $growids = [ Item::SEEDS,Item::CARROT,Item::POTATO,Item::BEETROOT,Item::SUGAR_CANE,Item::SUGARCANE_BLOCK,Item::PUMPKIN_SEEDS,Item::MELON_SEEDS,Item::DYE,Item::CACTUS ];
	public $blockids = [ Block::WHEAT_BLOCK,Block::CARROT_BLOCK,Block::POTATO_BLOCK,Block::BEETROOT_BLOCK,Block::SUGARCANE_BLOCK,Block::SUGARCANE_BLOCK,Block::PUMPKIN_STEM,Block::MELON_STEM,127,Block::CACTUS ];
	public $farmconfig, $configdata;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->farmlist = new Config ( $this->getDataFolder () . "farmlist.yml", Config::YAML );
		$this->farmdata = $this->farmlist->getAll ();
		$this->farmconfig = new Config ( $this->getDataFolder () . "speed.yml", Config::YAML, array ("growing-time" => 1200,"vip-growing-time" => 600 ) );
		$this->configdata = $this->farmconfig->getAll ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new FarmsTask ( $this ), 20 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->farmlist->setAll ( $this->farmdata );
		$this->farmlist->save ();
		$this->farmconfig->save ();
	}
	public function onBlock(PlayerInteractEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "Farms" ) and ! $event->getPlayer ()->hasPermission ( "Farms.VIP" )) return;
		$block = $event->getBlock ()->getSide ( 1 );
		
		if ($event->getItem ()->getID () == Item::DYE and $event->getItem ()->getDamage () == 3) {
			$tree = $event->getBlock ()->getSide ( $event->getFace () );
			if ($tree->getID () == Item::TRUNK or $tree->getDamage () == 3) {
				$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock ()->getSide ( $event->getFace () ), new CocoaBeanBlock ( $event->getFace () ), true, true );
				return;
			}
		}
		if ($event->getBlock ()->getID () == Item::FARMLAND or $event->getBlock ()->getID () == Item::SAND) {
			foreach ( $this->growids as $index => $growid )
				if ($event->getItem ()->getID () == $growid) {
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['id'] = $this->blockids [$index];
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['damage'] = 0;
					$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['level'] = $block->getLevel ()->getFolderName ();
					if ($event->getPlayer ()->hasPermission ( "Farms.VIP" )) {
						$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['time'] = $this->configdata ["vip-growing-time"];
					} else {
						$this->farmdata [$block->x . "." . $block->y . "." . $block->z] ['time'] = $this->configdata ["growing-time"];
					}
					break;
				}
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$itemid = $event->getItem ()->getID ();
		
		foreach ( $this->growids as $index => $growid )
			if ($itemid == $growid and isset ( $this->farmdata [$block->x . "." . $block->y . "." . $block->z] )) {
				unset ( $this->farmdata [$block->x . "." . $block->y . "." . $block->z] );
			}
	}
	public function Farms() {
		foreach ( array_keys ( $this->farmdata ) as $p ) {
			if (-- $this->farmdata [$p] ['time'] > 0) continue;
			
			$e = explode ( ".", $p );
			if (! isset ( $this->farmdata [$p] ['id'] )) {
				unset ( $this->farmdata [$p] );
				return;
			}
			$id = $this->farmdata [$p] ['id'];
			
			switch ($id) {
				case Item::WHEAT_BLOCK :
				case Item::CARROT_BLOCK :
				case Item::POTATO_BLOCK :
				case Item::BEETROOT_BLOCK :
					if (++ $this->farmdata [$p] ['damage'] >= 8) {
						// GROW TIME IS OVER
						unset ( $this->farmdata [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] );
					if (isset ( $this->farmdata [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmdata [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					$level->setBlock ( $vector3, $block );
					break;
				case Item::SUGARCANE_BLOCK :
				case Item::CACTUS :
					if (++ $this->farmdata [$p] ['damage'] >= 4) {
						// GROW TIME IS OVER
						unset ( $this->farmdata [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1] + $this->farmdata [$p] ['damage'], $e [2] );
					$block = Block::get ( $this->farmdata [$p] ['id'], 0 );
					if (isset ( $this->farmdata [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmdata [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if ($level->getBlock ( $vector3 )->getID () != Item::AIR) {
						// THAT SUGAR CANE IS SOMETHING DIFFERENT USE..?
						unset ( $this->farmdata [$p] );
						return;
					}
					$level->setBlock ( $vector3, $block );
					break;
				case Item::PUMPKIN_STEM :
					if (isset ( $this->farmdata [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmdata [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if (++ $this->farmdata [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++)
							for($b = - 1; $b <= 1; $b ++) {
								$ground_vector = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$vector3 = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								
								if ($level->getBlock ( $vector3 )->getID () != Item::AIR) break;
								if ($level->getBlock ( $ground_vector )->getID () != Item::FARMLAND) break;
								
								$level->setBlock ( $vector3, Block::get ( Item::PUMPKIN ) );
								unset ( $this->farmdata [$p] );
								return;
							}
						if (isset ( $this->farmdata [$p] )) {
							// GROW TIME IS OVER
							unset ( $this->farmdata [$p] );
							return;
						}
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] );
					$level->setBlock ( $vector3, $block );
					break;
				case Item::MELON_STEM :
					if (isset ( $this->farmdata [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmdata [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if (++ $this->farmdata [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++)
							for($b = - 1; $b <= 1; $b ++) {
								$ground = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$vector3 = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								if ($level->getBlock ( $vector3 )->getID () != Item::AIR) break;
								if ($level->getBlock ( $ground )->getID () != Item::FARMLAND) break;
								
								$level->setBlock ( $vector3, Block::get ( Item::MELON_BLOCK, 0 ) );
								unset ( $this->farmdata [$p] );
								return;
							}
						if (isset ( $this->farmdata [$p] )) {
							// GROW TIME IS OVER
							unset ( $this->farmdata [$p] );
							return;
						}
					}
					if (! isset ( $this->farmdata [$p] )) {
						unset ( $this->farmdata [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmdata [$p] ['id'], $this->farmdata [$p] ['damage'] );
					$level->setBlock ( $vector3, $block );
					break;
			}
			$this->farmdata [$p] ['time'] = $this->configdata ["growing-time"];
		}
	}
}
class CocoaBeanBlock extends Flowable {
	public function __construct($face = 0) {
		parent::__construct ( 127, $this->getBeanFace ( $face ), "Cocoa Bean" );
		$this->isActivable = true;
		$this->hardness = 0;
		$this->treeFace = $this->getTreeFace ();
	}
	public function getDrops(Item $item) {
		$drops = [ ];
		echo "check meta:" . $this->meta;
		echo "check full meta:" . $this->meta;
		if ($this->meta == $this->meta % 4 + 8) {
			$drops [] = [ 351,3,mt_rand ( 1, 4 ) ];
		} else {
			$drops [] = [ 351,3,1 ];
		}
		return $drops;
	}
	public function getTree() {
		return $this->getSide ( $this->treeFace );
	}
	public function getTreeFace() {
		switch ($this->meta % 4) {
			case 0 :
				return 2;
			case 1 :
				return 5;
			case 2 :
				return 3;
			case 3 :
				return 4;
			default :
				return rand ( 2, 5 );
		}
	}
	public function getBeanFace($face = 0) {
		switch ($face) {
			case 2 :
				return 0;
			case 3 :
				return 2;
			case 4 :
				return 3;
			case 5 :
				return 1;
		}
	}
}
class CocoaBean extends Item {
	public function __construct($meta = 0, $count = 1) {
		$this->block = Block::get ( 127 );
		parent::__construct ( 351, 3, $count, "Cocoa Bean" );
	}
	public function onActivate(Level $level, Player $player, Block $block, Block $target, $face, $fx, $fy, $fz) {
		return true;
	}
}
?>
<?php

namespace ifteam\Farms;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Flowable;
use pocketmine\level\Level;
use pocketmine\Player;

class Farms extends PluginBase implements Listener {
	/**
	 *
	 * @var Config
	 */
	public $farmConfig, $speedConfig;
	
	/**
	 *
	 * @var array
	 */
	public $farmData, $speedData;
	
	/**
	 *
	 * @var array
	 */
	public $crops = [ [ "item" => Item::SEEDS,"block" => Block::WHEAT_BLOCK ],[ "item" => Item::CARROT,"block" => Block::CARROT_BLOCK ],[ "item" => Item::POTATO,"block" => Block::POTATO_BLOCK ],[ "item" => Item::BEETROOT,"block" => Block::BEETROOT_BLOCK ],[ "item" => Item::SUGAR_CANE,"block" => Block::SUGARCANE_BLOCK ],[ "item" => Item::SUGARCANE_BLOCK,"block" => Block::SUGARCANE_BLOCK ],[ "item" => Item::PUMPKIN_SEEDS,"block" => Block::PUMPKIN_STEM ],[ "item" => Item::MELON_SEEDS,"block" => Block::MELON_STEM ],[ "item" => Item::DYE,"block" => 127 ],[ "item" => Item::CACTUS,"block" => Block::CACTUS ] ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->farmConfig = new Config ( $this->getDataFolder () . "farmlist.yml", Config::YAML );
		$this->farmData = $this->farmConfig->getAll ();
		
		$this->speedConfig = new Config ( $this->getDataFolder () . "speed.yml", Config::YAML, [ "growing-time" => 1200,"vip-growing-time" => 600 ] );
		$this->speedData = $this->speedConfig->getAll ();
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new FarmsTask ( $this ), 20 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->farmConfig->setAll ( $this->farmData );
		$this->farmConfig->save ();
		
		$this->speedConfig->save ();
	}
	public function onBlock(PlayerInteractEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "Farms" ) and ! $event->getPlayer ()->hasPermission ( "Farms.VIP" )) return;
		$block = $event->getBlock ()->getSide ( 1 );
		
		// Cocoa been
		if ($event->getItem ()->getId () == Item::DYE and $event->getItem ()->getDamage () == 3) {
			$tree = $event->getBlock ()->getSide ( $event->getFace () );
			// Jungle wood
			if ($tree->getId () == Block::WOOD and $tree->getDamage () == 3) {
				$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock ()->getSide ( $event->getFace () ), new CocoaBeanBlock ( $event->getFace () ), true, true );
				return;
			}
		}
		
		// Farmland or sand
		if ($event->getBlock ()->getId () == Item::FARMLAND or $event->getBlock ()->getId () == Item::SAND) {
			foreach ( $this->crops as $crop ) {
				if ($event->getItem ()->getId () == $crop ["item"]) {
					$key = $block->x . "." . $block->y . "." . $block->z;
					
					$this->farmData [$key] ['id'] = $crop ["block"];
					$this->farmData [$key] ['damage'] = 0;
					$this->farmData [$key] ['level'] = $block->getLevel ()->getFolderName ();
					$this->farmData [$key] ['time'] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					$this->farmData [$key] ['growtime'] = $this->speedData [$event->getPlayer ()->hasPermission ( "Farms.VIP" ) ? "vip-growing-time" : "growing-time"];
					break;
				}
			}
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$itemId = $event->getItem ()->getId ();
		
		$key = $block->x . "." . $block->y . "." . $block->z;
		
		foreach ( $this->crops as $crop ) {
			if ($itemId == $crop ["item"] and isset ( $this->farmData [$key] )) {
				unset ( $this->farmData [$key] );
			}
		}
	}
	public function tick() {
		foreach ( $this->farmData as $key => $farm ) {
			$progress = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->farmData [$key] ['time'];
			if ($progress < $this->farmData [$key] ['growtime']) continue;
			$coordinates = explode ( ".", $key );
			$position = new Vector3 ( $coordinates [0], $coordinates [1], $coordinates [2] );
			
			if (! isset ( $farm ['id'] )) {
				unset ( $this->farmData [$key] );
				continue;
			}
			$level = isset ( $farm ['level'] ) ? $this->getServer ()->getLevelByName ( $farm ['level'] ) : $this->getServer ()->getDefaultLevel ();
			
			switch ($this->farmData [$key] ['id']) {
				case Block::WHEAT_BLOCK :
				case Block::CARROT_BLOCK :
				case Block::POTATO_BLOCK :
				case Block::BEETROOT_BLOCK :
					$this->updateNormalCrops ( $this->farmData [$key] ['id'], $this->farmData [$key] ['damage'], $level, $position, $key );
					break;
				
				case Block::SUGARCANE_BLOCK :
				case Block::CACTUS :
					$this->updateVerticalGrowingCrops ( $this->farmData [$key] ['id'], $this->farmData [$key] ['damage'], $level, $position, $key );
					break;
				
				case Block::PUMPKIN_STEM :
				case Block::MELON_STEM :
					$this->updateHorizontalGrowingCrops ( $this->farmData [$key] ['id'], $this->farmData [$key] ['damage'], $level, $position, $key );
			}
			
			$farm ['time'] = $this->speedData ["growing-time"];
		}
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
	/**
	 *
	 * @param int $id        	
	 * @param int $damage        	
	 * @param Level $level        	
	 * @param Vector3 $position        	
	 */
	public function updateNormalCrops(&$id, &$damage, Level $level, Vector3 $position, $key = null) {
		$damage ++;
		if ($damage >= 8) { // FULL GROWN!
			if (isset ( $this->farmData [$key] )) unset ( $this->farmData [$key] );
			return;
		}
		$level->setBlock ( $position, Block::get ( $id, $damage ) );
	}
	
	/**
	 *
	 * @param int $id        	
	 * @param int $damage        	
	 * @param Level $level        	
	 * @param Vector3 $position        	
	 */
	public function updateVerticalGrowingCrops(&$id, &$damage, Level $level, Vector3 $position, $key = null) {
		if (++ $damage >= 4) { // FULL GROWN!
			if (isset ( $this->farmData [$key] )) unset ( $this->farmData [$key] );
			return;
		}
		
		$cropPosition = $position->add ( 0, $damage, 0 );
		if ($level->getBlock ( $cropPosition )->getId () !== Item::AIR) { // SOMETHING EXISTS
			if (isset ( $this->farmData [$key] )) unset ( $this->farmData [$key] );
			return;
		}
		$level->setBlock ( $position, Block::get ( $id, 0 ) );
	}
	
	/**
	 *
	 * @param int $id        	
	 * @param int $damage        	
	 * @param Level $level        	
	 * @param Vector3 $position        	
	 */
	public function updateHorizontalGrowingCrops(&$id, &$damage, Level $level, Vector3 $position, $key = null) {
		$cropBlock = null;
		switch ($id) {
			case Block::PUMPKIN_STEM :
				$cropBlock = Block::get ( Block::PUMPKIN );
				break;
			case Block::MELON_STEM :
				$cropBlock = Block::get ( Block::MELON_BLOCK );
				break;
		}
		
		if (++ $damage >= 8) { // FULL GROWN!
			for($xOffset = - 1; $xOffset <= 1; $xOffset ++) {
				for($zOffset = - 1; $zOffset <= 1; $zOffset ++) {
					$cropPosition = $position->add ( $xOffset, 0, $zOffset );
					
					if ($xOffset === 0 and $zOffset === 0) { // STEM
						continue;
					}
					
					if ($level->getBlock ( $cropPosition )->getId () === Item::AIR) {
						$level->setBlock ( $cropPosition, $cropBlock );
						if (isset ( $this->farmData [$key] )) unset ( $this->farmData [$key] );
						return;
					}
				}
			}
			if (isset ( $this->farmData [$key] )) {
				unset ( $this->farmData [$key] );
				return;
			}
		}
		$level->setBlock ( $position, Block::get ( $id, $damage ) );
		return;
	}
}
class CocoaBeanBlock extends Flowable {
	public function __construct($face = 0) {
		parent::__construct ( 127, $this->getBeanFace ( $face ), "Cocoa Bean" );
		$this->treeFace = $this->getTreeFace ();
	}
	public function canBeActivated() {
		return true;
	}
	public function getDrops(Item $item) {
		$drops = [ ];
		
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
			default :
			// TODO: $face === 0 등의 다른 경우에 대한 처리를 추가해야 함
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
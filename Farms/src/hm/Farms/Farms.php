<?php

namespace hm\Farms;

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
	/** @var Config */
    public $farmConfig, $speedConfig;

    /** @var array */
    public $farmData, $speedData;

    /** @var array */
	public $crops = [
        ["item" => Item::SEEDS,           "block" => Block::WHEAT_BLOCK],
        ["item" => Item::CARROT,          "block" => Block::CARROT_BLOCK],
        ["item" => Item::POTATO,          "block" => Block::POTATO_BLOCK],
        ["item" => Item::BEETROOT,        "block" => Block::BEETROOT_BLOCK],
        ["item" => Item::SUGAR_CANE,      "block" => Block::SUGARCANE_BLOCK],
        ["item" => Item::SUGARCANE_BLOCK, "block" => Block::SUGARCANE_BLOCK],
        ["item" => Item::PUMPKIN_SEEDS,   "block" => Block::PUMPKIN_STEM],
        ["item" => Item::MELON_SEEDS,     "block" => Block::MELON_STEM],
        ["item" => Item::DYE,             "block" => 127],
        ["item" => Item::CACTUS,          "block" => Block::CACTUS]
    ];

	public function onEnable() {
		@mkdir ( $this->getDataFolder () );

		$this->farmConfig = new Config ( $this->getDataFolder () . "farmlist.yml", Config::YAML );
		$this->farmData = $this->farmConfig->getAll ();

		$this->speedConfig = new Config ( $this->getDataFolder () . "speed.yml", Config::YAML, ["growing-time" => 1200,"vip-growing-time" => 600]);
		$this->speedData = $this->speedConfig->getAll ();

		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new FarmsTask ( $this ), 20 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->farmConfig->setAll($this->farmData);
		$this->farmConfig->save();

		$this->speedConfig->save();
	}
	public function onBlock(PlayerInteractEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "Farms" ) and ! $event->getPlayer ()->hasPermission ( "Farms.VIP" )) return;
		$block = $event->getBlock ()->getSide ( 1 );

        //Cocoa been
		if ($event->getItem ()->getId() == Item::DYE and $event->getItem ()->getDamage () == 3) {
			$tree = $event->getBlock ()->getSide ( $event->getFace () );
            //Jungle wood
			if ($tree->getId() == Block::WOOD and $tree->getDamage () == 3) {
				$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock ()->getSide ( $event->getFace () ), new CocoaBeanBlock ( $event->getFace () ), true, true );
				return;
			}
		}

        //Farmland or sand
		if ($event->getBlock()->getId() == Item::FARMLAND or $event->getBlock()->getId() == Item::SAND) {
			foreach($this->crops as $crop){
                if($event->getItem()->getId() == $crop["item"]){
                    $key = $block->x . "." . $block->y . "." . $block->z;

                    $this->farmData[$key]['id'] = $crop["block"];
                    $this->farmData[$key]['damage'] = 0;
                    $this->farmData[$key]['level'] = $block->getLevel()->getFolderName();
                    $this->farmData[$key]['time'] = $this->speedData[$event->getPlayer()->hasPermission("Farms.VIP") ? "vip-growing-time" : "growing-time"];
                    break;
                }
            }
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$itemId = $event->getItem ()->getId();

        $key = $block->x . "." . $block->y . "." . $block->z;

		foreach($this->crops as $crop){
            if($itemId == $crop["item"] and isset ($this->farmData[$key])){
                unset($this->farmData[$key]);
            }
        }
	}

	public function Farms() {
		foreach ( array_keys ( $this->farmData ) as $p ) {
			if (-- $this->farmData [$p] ['time'] > 0) continue;
			
			$e = explode ( ".", $p );
			if (! isset ( $this->farmData [$p] ['id'] )) {
				unset ( $this->farmData [$p] );
				return;
			}
			$id = $this->farmData [$p] ['id'];
			
			switch ($id) {
				case Item::WHEAT_BLOCK :
				case Item::CARROT_BLOCK :
				case Item::POTATO_BLOCK :
				case Item::BEETROOT_BLOCK :
					if (++ $this->farmData [$p] ['damage'] >= 8) {
						// GROW TIME IS OVER
						unset ( $this->farmData [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmData [$p] ['id'], $this->farmData [$p] ['damage'] );
					if (isset ( $this->farmData [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmData [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					$level->setBlock ( $vector3, $block );
					break;
				case Item::SUGARCANE_BLOCK :
				case Item::CACTUS :
					if (++ $this->farmData [$p] ['damage'] >= 4) {
						// GROW TIME IS OVER
						unset ( $this->farmData [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1] + $this->farmData [$p] ['damage'], $e [2] );
					$block = Block::get ( $this->farmData [$p] ['id'], 0 );
					if (isset ( $this->farmData [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmData [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if ($level->getBlock ( $vector3 )->getID () != Item::AIR) {
						// THAT SUGAR CANE IS SOMETHING DIFFERENT USE..?
						unset ( $this->farmData [$p] );
						return;
					}
					$level->setBlock ( $vector3, $block );
					break;
				case Item::PUMPKIN_STEM :
					if (isset ( $this->farmData [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmData [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if (++ $this->farmData [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++)
							for($b = - 1; $b <= 1; $b ++) {
								$ground_vector = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$vector3 = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								
								if ($level->getBlock ( $vector3 )->getID () != Item::AIR) break;
								if ($level->getBlock ( $ground_vector )->getID () != Item::FARMLAND) break;
								
								$level->setBlock ( $vector3, Block::get ( Item::PUMPKIN ) );
								unset ( $this->farmData [$p] );
								return;
							}
						if (isset ( $this->farmData [$p] )) {
							// GROW TIME IS OVER
							unset ( $this->farmData [$p] );
							return;
						}
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmData [$p] ['id'], $this->farmData [$p] ['damage'] );
					$level->setBlock ( $vector3, $block );
					break;
				case Item::MELON_STEM :
					if (isset ( $this->farmData [$p] ['level'] )) {
						$level = $this->getServer ()->getLevelByName ( $this->farmData [$p] ['level'] );
					} else {
						$level = $this->getServer ()->getDefaultLevel ();
					}
					if (++ $this->farmData [$p] ['damage'] >= 8) {
						for($i = - 1; $i <= 1; $i ++)
							for($b = - 1; $b <= 1; $b ++) {
								$ground = new Vector3 ( $e [0] + $i, $e [1] - 1, $e [2] + $b );
								$vector3 = new Vector3 ( $e [0] + $i, $e [1], $e [2] + $b );
								if ($level->getBlock ( $vector3 )->getID () != Item::AIR) break;
								if ($level->getBlock ( $ground )->getID () != Item::FARMLAND) break;
								
								$level->setBlock ( $vector3, Block::get ( Item::MELON_BLOCK, 0 ) );
								unset ( $this->farmData [$p] );
								return;
							}
						if (isset ( $this->farmData [$p] )) {
							// GROW TIME IS OVER
							unset ( $this->farmData [$p] );
							return;
						}
					}
					if (! isset ( $this->farmData [$p] )) {
						unset ( $this->farmData [$p] );
						return;
					}
					$vector3 = new Vector3 ( $e [0], $e [1], $e [2] );
					$block = Block::get ( $this->farmData [$p] ['id'], $this->farmData [$p] ['damage'] );
					$level->setBlock ( $vector3, $block );
					break;
			}
			$this->farmData [$p] ['time'] = $this->speedData ["growing-time"];
		}
	}
}
class CocoaBeanBlock extends Flowable {
	public function __construct($face = 0) {
		parent::__construct ( 127, $this->getBeanFace ( $face ), "Cocoa Bean" );
		$this->isActivable = true;
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
            default:
                //TODO: $face === 0 등의 다른 경우에 대한 처리를 추가해야 함
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
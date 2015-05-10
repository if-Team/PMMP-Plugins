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
		
		$this->speedConfig = new Config ( $this->getDataFolder () . "speed.yml", Config::YAML, [ "normal-growth-period" => 1200,"vip-growth-period" => 600 ] );
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
					
					$this->farmData[$key]['id'] = $crop ["block"];
					$this->farmData[$key]['damage'] = 0;
					$this->farmData[$key]['level'] = $block->getLevel()->getFolderName();
					$this->farmData[$key]['creation-time'] = time();
					$this->farmData[$key]['growth-period'] = $this->speedData[$event->getPlayer()->hasPermission("Farms.VIP") ? "vip-growth-period" : "normal-growth-period"];
					break;
				}
			}
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$key = $event->getBlock ()->x . "." . $event->getBlock ()->y . "." . $event->getBlock ()->z;
		foreach ( $this->crops as $crop ) {
			if ( $event->getItem ()->getId () == $crop ["item"] and isset ( $this->farmData [$key] )) {
				unset ( $this->farmData [$key] );
			}
		}
	}

	public function tick(){
		foreach(array_keys($this->farmData) as $key){
            if(!isset($this->farmData[$key]["id"])){
                unset($this->farmData[$key]);
                continue;
            }

			$progress = time() - $this->farmData[$key]["creation-time"];
			if($progress < $this->farmData[$key]["growth-period"]){
                continue;
            }

            $level = isset($this->farmData[$key]["level"]) ? $this->getServer()->getLevelByName($this->farmData[$key]["level"]) : $this->getServer()->getDefaultLevel();

            $coordinates = explode(".", $key);
			$position = new Vector3($coordinates[0], $coordinates[1], $coordinates[2]);

            if($this->updateCrops($key, $level, $position)){
                unset($this->farmData[$key]);
                continue;
            }
		}
	}

    /**
     * @param $key
     * @param Level $level
     * @param Vector3 $position
     * @return bool
     */
    public function updateCrops($key, Level $level, Vector3 $position){
        switch($this->farmData[$key]['id']){
            case Block::WHEAT_BLOCK:
            case Block::CARROT_BLOCK:
            case Block::POTATO_BLOCK:
            case Block::BEETROOT_BLOCK:
                return $this->updateNormalCrops($key, $level, $position);

            case Block::SUGARCANE_BLOCK:
            case Block::CACTUS:
                return $this->updateVerticalGrowingCrops($key, $level, $position);

            case Block::PUMPKIN_STEM :
            case Block::MELON_STEM :
                return $this->updateHorizontalGrowingCrops($key, $level, $position);

            default:
                return true;
        }
    }

    /**
     * @param $key
     * @param Level $level
     * @param Vector3 $position
     * @return bool
     */
	public function updateNormalCrops($key, Level $level, Vector3 $position){
		if(++$this->farmData[$key]["damage"] >= 8){ //FULL GROWN!
			return true;
		}

		$level->setBlock($position, Block::get($this->farmData[$key]["id"], $this->farmData[$key]["damage"]));
        return false;
	}

    /**
     * @param $key
     * @param Level $level
     * @param Vector3 $position
     * @return bool
     */
	public function updateVerticalGrowingCrops($key, Level $level, Vector3 $position){
		if(++$this->farmData[$key]["damage"] >= 4){ //FULL GROWN!
			return true;
		}
		
		$cropPosition = $position->setComponents($position->x, $position->y + $this->farmData[$key]["damage"], $position->z);
		if($level->getBlockIdAt($cropPosition->x, $cropPosition->y, $cropPosition->z) !== Item::AIR){ //SOMETHING EXISTS
			return true;
		}

		$level->setBlock($cropPosition, Block::get($this->farmData[$key]["id"], 0));
        return false;
	}

    /**
     * @param $key
     * @param Level $level
     * @param Vector3 $position
     * @return bool
     */
	public function updateHorizontalGrowingCrops($key, Level $level, Vector3 $position){
		$cropBlock = null;

		switch($this->farmData[$key]["id"]){
			case Block::PUMPKIN_STEM:
				$cropBlock = Block::get(Block::PUMPKIN);
				break;

			case Block::MELON_STEM:
				$cropBlock = Block::get(Block::MELON_BLOCK);
				break;

            default: //NOT A HORIZONTAL GROWING CROP
                return true;
		}

		if(++$this->farmData[$key]["damage"] >= 8){ //FULL GROWN!
            static $offsets = [[-1, -1], [-1, 0], [-1, 1], [0, -1], [0, 1], [1, -1], [1, 0], [1, 1]];
            shuffle($offsets);

            foreach($offsets as $offset){
                $cropPosition = $position->setComponents($position->x + $offset[0], $position->y, $position->z + $offset[1]);
                if($level->getBlockIdAt($cropPosition->x, $cropPosition->y, $cropPosition->z) === Item::AIR){
                    $level->setBlock($cropPosition, $cropBlock);
                    break;
                }
            }
            return true;
		}

		$level->setBlock($position, Block::get($this->farmData[$key]["id"], $this->farmData[$key]["damage"]));
        return false;
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
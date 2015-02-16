<?php

namespace hm\GoodMorning;

use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\network\protocol\ChatPacket;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\math\Vector3;
use pocketmine\level\Position;

class GoodMorning extends PluginBase implements Listener {
	public $placeQueeue = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function PlaceQueeue(BlockPlaceEvent $event) {
		$block = $event->getBlock ();
		
		if ($block->getID () === Item::BED_BLOCK) {
			$player = $event->getPlayer ();
			$this->placeQueeue [$player->getName ()] = 1;
		}
	}
	public function BlockTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		
		if (isset ( $this->placeQueeue [$player->getName ()] )) {
			unset ( $this->placeQueeue [$player->getName ()] );
			return;
		}
		if ($block->getID () == Item::BED_BLOCK) {
			$event->setCancelled ();
			
			$blockNorth = $block->getSide ( 2 );
			$blockSouth = $block->getSide ( 3 );
			$blockEast = $block->getSide ( 5 );
			$blockWest = $block->getSide ( 4 );
			
			if ($blockNorth->getID () === Item::BED_BLOCK) {
				$b = $blockNorth;
			} elseif ($blockSouth->getID () === Item::BED_BLOCK) {
				$b = $blockSouth;
			} elseif ($blockEast->getID () === Item::BED_BLOCK) {
				$b = $blockEast;
			} elseif ($blockWest->getID () === Item::BED_BLOCK) {
				$b = $blockWest;
			} else {
				return;
			}
			$time = $this->getServer ()->getDefaultLevel ()->getTime () % Level::TIME_FULL;
			$isNight = ($time >= Level::TIME_NIGHT and $time < Level::TIME_SUNRISE);
			
			if ($player instanceof Player and ! $isNight and $player->sleepOn ( $b ) === \true) {
				$pk = new ChatPacket ();
				$pk->message = "Take a rest";
				$player->dataPacket ( $pk );
			} else if ($player instanceof Player and $isNight) {
				$pk = new ChatPacket ();
				$pk->message = "Take a rest";
				$player->dataPacket ( $pk );
			}
		}
	}
}
?>
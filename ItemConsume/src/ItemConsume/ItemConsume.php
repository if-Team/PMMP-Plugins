<?php

namespace ItemConsume;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\tile\Tile;
use pocketmine\inventory\InventoryHolder;
use pocketmine\block\Chest;
use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\block\BlockEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\block\Block;

class ItemConsume extends PluginBase implements Listener {
	public $itemQueue = [ ];
	public $breakQueue = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDrops(ItemSpawnEvent $event) {
		$e = $event->getEntity ();
		$vec = "{$e->x}:{$e->y}:{$e->z}";
		if (isset ( $this->itemQueue [$vec] )) {
			unset ( $this->itemQueue [$vec] );
			
			$reflection_class = new \ReflectionClass ( $e );
			
			foreach ($reflection_class->getProperties() as $properties){
				if($properties->getName() == 'age'){
					$property = $reflection_class->getProperty ( 'age' );
					$property->setAccessible ( true );
					if($property->getValue($event->getEntity ()) == 0)
						$property->setValue ( $event->getEntity (), 7000 );
				}
			}
		}
	}
	public function onAir(BlockUpdateEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ))
			if ($block->getId () == Block::AIR)
				foreach ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["drop"] as $drop )
					if ($drop [2] > 0) {
						if(isset($this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"]["player"]))
							$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"]["player"]->getInventory()->addItem(Item::get(...$drop));
						unset ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] );
						return;
					}
			$x = $block->x + 0.5;
			$y = $block->y + 0.5;
			$z = $block->z + 0.5;
				
			unset ( $this->itemQueue ["{$x}:{$y}:{$z}"] );
	}
	public function onBreak(BlockBreakEvent $event) {
		if ($event->isCancelled ())
			return;
		
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		$drops = $block->getDrops ( $event->getItem () );
		
		$x = $block->x + 0.5;
		$y = $block->y + 0.5;
		$z = $block->z + 0.5;
		
		$tile = $block->getLevel ()->getTile ( $block );
		if ($tile instanceof Tile) {
			if ($tile instanceof InventoryHolder) {
				if ($tile instanceof Chest) {
					$tile->unpair ();
				}
				foreach ( $tile->getInventory ()->getContents () as $chestItem ) {
					if (! ($player instanceof Player)) {
						$block->getLevel ()->dropItem ( $block, $chestItem );
					} else {
						$player->getInventory ()->addItem ( $chestItem );
					}
				}
			}
		}
		if ($event->getItem () instanceof Item) {
			$event->getItem ()->useOn ( $block );
			if ($event->getItem ()->isTool () and $event->getItem ()->getDamage () >= $event->getItem ()->getMaxDurability ()) {
				$event->getPlayer ()->getInventory ()->setItemInHand ( Item::get ( Item::AIR, 0, 0 ) );
			}
		}
		
		if (! ($player instanceof Player)) {
			foreach ( $drops as $drop ) {
				if ($drop [2] > 0) {
					$event->getBlock()->getLevel()->dropItem($event->getBlock()->add(0.5, 0.5, 0.5), Item::get(...$drop));
				}
			}
		} else if ($player->isSurvival ()) {
			$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["drop"] = $drops;
			$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["player"] = $player;
			$this->itemQueue ["{$x}:{$y}:{$z}"] = $drops;
		}
	}
}

?>
<?php

namespace TreeChopper;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Air;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\block\BlockUpdateEvent;

class TreeChopper extends PluginBase implements Listener {
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
			if ($block->getId () == Block::AIR){
				if(isset($this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"]["player"])){
					$this->treeDetect ( $block, $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"]["player"] );
					foreach ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["drop"] as $drop )
						if ($drop [2] > 0) {
							$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"]["player"]->getInventory()->addItem(Item::get(...$drop));
							unset ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] );
							return;
						}
				}
			}
			$x = $block->x + 0.5;
			$y = $block->y + 0.5;
			$z = $block->z + 0.5;
				
			unset ( $this->itemQueue ["{$x}:{$y}:{$z}"] );
	}
	public function onBreak(BlockBreakEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "TreeChopper" )) return;
		
		if ($event->getBlock ()->getId () == Block::LOG) {
			$block = $event->getBlock ();
			$down = $block->getLevel ()->getBlockIdAt ( $block->x, $block->y - 1, $block->z );
			if ($down != Block::GRASS and $down != Block::DIRT) return;

			$x = $block->x + 0.5;
			$y = $block->y + 0.5;
			$z = $block->z + 0.5;
			$drops = $block->getDrops ( $event->getItem () );
			
			$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["drop"] = $drops;
			$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] ["player"] = $event->getPlayer ();
			$this->itemQueue ["{$x}:{$y}:{$z}"] = $drops;
		}
	}
	public function treeDetect(Block $block, Player $player, $isdrop = true) {
		if ($block->getId () == Block::LOG or $block->getId () == Block::LEAVE) {
			$drops = $block->getDrops ( $player->getInventory ()->getItemInHand () );
			if ($isdrop == true) foreach ( $drops as $drop )
				if ($drop [2] > 0) $player->getInventory ()->addItem ( Item::get ( ...$drop ) );
			$block->getLevel ()->setBlock ( $block, new Air (), false, false );
		}
		$id = $block->getLevel ()->getBlockIdAt ( $block->x, $block->y - 1, $block->z );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( 0, - 1, 0 ) ), $player );
		
		$id = $block->getLevel ()->getBlockIdAt ( $block->x, $block->y + 1, $block->z );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( 0, 1, 0 ) ), $player );
		
		$id = $block->getLevel ()->getBlockIdAt ( $block->x, $block->y, $block->z - 1 );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( 0, 0, - 1 ) ), $player );
		
		$id = $block->getLevel ()->getBlockIdAt ( $block->x, $block->y, $block->z + 1 );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( 0, 0, 1 ) ), $player );
		
		$id = $block->getLevel ()->getBlockIdAt ( $block->x - 1, $block->y, $block->z );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( - 1, 0, 0 ) ), $player );
		
		$id = $block->getLevel ()->getBlockIdAt ( $block->x + 1, $block->y, $block->z );
		if ($id == Block::LOG or $id == Block::LEAVE) $this->treeDetect ( $block->getLevel ()->getBlock ( $block->add ( 1, 0, 0 ) ), $player );
	}
}

?>
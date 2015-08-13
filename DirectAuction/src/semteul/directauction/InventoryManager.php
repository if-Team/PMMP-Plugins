<?php

namespace semteul\directauction;

use chalk\utils\Messages;

use pocketmine\Player;
use pocketmine\item\Item;



class InventoryManager {
	
	public $inventory, $size, $player;
	
	
	
	public function __construct(Player $player) {
		$this->player = $player;
		$this->inventory = $player->getInventory();
		$this->size = $this->inventory->getSize();
	}
	
	
	
	public function getItemInHand() {
		return $this->inventory->getItemInHand();
	}
	
	
	//total item count
	public function getCount($id, $damage, $tag = "") {
		$count = 0;
		for($e = 0; $e < $this->size; $e++) {
			$item = $this->inventory->getItem($e);
			if($item->getId() == $id && $item->getDamage() == $damage/* && $item->getCompoundTag() === $tag*/) {
				$count += $item->getCount();
			}
		}
		return $count;
	}
	
	
	//add item inventory or drop it
	public function addItem($item) {
		$max = $item->getMaxStackSize();
		$count = $item->getCount();
		
		for($e = 0; $e < $this->size; $e++) {
			$item2 = $this->inventory->getItem($e);
			
			if($item->getId() === $item2->getId() && $item->getDamage() === $item2->getDamage()/* && $item->getCompoundTag() === $item2->getCompoundTag()*/) {
				$extra = $max - $item2->getCount();
				
				if($extra > $count) {
					$item2->setCount($item2->getCount() + $count);
					$this->inventory->setItem($e, $item2);
					$count = 0;
					break;
				}else {
					$count -= $extra;
					$item2->setCount($max);
					$this->inventory->setItem($e, $item2);
				}
			}
		}
		for($e = 0; $e < $this->size; $e++) {
			$item2 = $this->inventory->getItem($e);
			
			if($item2->getId() === Item::AIR) {
				if($count > $max) {
					$this->inventory->setItem($e, Item::get($item->getId(), $item->getDamage(), $max/*, $item->getCompoundTag()*/));
					$count -= $max;
				}else {
					$this->inventory->setItem($e, Item::get($item->getId(), $item->getDamage(), $count/*, $item->getCompoundTag()*/));
					$count = 0;
					break;
				}
			}
		}
		if($count > 0) {
			$item->setCount($count);
			$this->player->getLevel()->dropItem($this->player->getPosition(), $item);
		}
	}
	
	
	//delete item in inventory or return false
	public function deleteItem($item) {
		$count = $item->getCount();
		$delete = 0;
		
		$item2 = $this->inventory->getItemInHand();
		if($item->getId() === $item2->getId() && $item->getDamage() === $item2->getDamage()/* && $item->getCompoundTag() === $item2->getCompoundTag()*/) {
			$count2 = $item2->getCount();
			
			if($count2 < $count) {
				$this->inventory->clear($this->inventory->getHeldItemIndex());
				$count -= $count2;
				$delete += $count2;
			}else {
				$item->setCount($count2 - $count);
				$this->inventory->setItemInHand($item);
				$delete += $count;
				$count = 0;
			}
		}
		for($e = 0; $e < $this->size; $e++) {
			$item2 = $this->inventory->getItem($e);
			
			if($item->getId() === $item2->getId() && $item->getDamage() === $item2->getDamage()/* && $item->getCompoundTag() === $item->getCompoundTag()*/) {
				$count2 = $item2->getCount();
				if($count2 <= $count) {
					$count -= $count2;
					$delete += $count2;
					$this->inventory->clear($e);
					if($count <= 0) {
						break;
					}
				}else {
					$count2 = $item2->getCount() - $count;
					$count = 0;
					$delete += $count2;
					$item2->setCount($count2);
					$this->inventory->setItem($e, $item2);
					break;
				}
			}
		}
		if($count === 0) {
			return;
		}else {
			$item->setCount($delete);
			$this->addItem($item);
			return false;
		}
	}
}
?>
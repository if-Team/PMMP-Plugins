<?php

namespace hm\FreeCraftingTable;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerRespawnEvent;

class FreeCraftingTable extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		foreach ( $player->getInventory ()->getContents () as $item ) {
			if ($item->getID () == Item::CRAFTING_TABLE)
				return;
		}
		$player->getInventory ()->addItem ( Item::get ( Item::CRAFTING_TABLE ) );
	}
	public function checkRespawn(PlayerRespawnEvent $event) {
		$player = $event->getPlayer ();
		foreach ( $player->getInventory ()->getContents () as $item ) {
			if ($item->getID () == Item::CRAFTING_TABLE)
				return;
		}
		$player->getInventory ()->addItem ( Item::get ( Item::CRAFTING_TABLE ) );
	}
}
?>
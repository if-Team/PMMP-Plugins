<?php

namespace ifteam\FreeCraftingTable;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerRespawnEvent;

class FreeCraftingTable extends PluginBase implements Listener {
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

    public function giveCraftingTable(Player $player){
        foreach($player->getInventory()->getContents() as $item){
            if($item->getId() === Item::CRAFTING_TABLE) return;
        }

        $player->getInventory()->addItem(Item::get(Item::CRAFTING_TABLE));
    }

	public function onJoin(PlayerJoinEvent $event){
        $this->giveCraftingTable($event->getPlayer());
	}

	public function onRespawn(PlayerRespawnEvent $event){
		$this->giveCraftingTable($event->getPlayer());
	}
}
?>
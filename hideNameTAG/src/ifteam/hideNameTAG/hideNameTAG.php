<?php

namespace ifteam\hideNameTAG;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\entity\Entity;

class hideNameTAG extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (! $event->getPlayer () instanceof Player) return;
		$event->getPlayer ()->setDataProperty ( Entity::DATA_SHOW_NAMETAG, Entity::DATA_TYPE_BYTE, 0 );
	}
	// ----------------------------------------------------------------------------------
}

?>
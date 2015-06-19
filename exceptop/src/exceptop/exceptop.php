<?php

namespace exceptop;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerKickEvent;

class exceptop extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onKick(PlayerKickEvent $event) {
		if ($event->getReason () == "disconnectionScreen.serverFull")
			if ($event->getPlayer ()->isOp ())
				$event->setCancelled ();
	}
}

?>
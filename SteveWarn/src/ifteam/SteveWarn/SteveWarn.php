<?php

namespace ifteam\SteveWarn;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;

class SteveWarn extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onLogin(PlayerLoginEvent $event) {
		if (strtolower ( $event->getPlayer ()->getName () ) == "steve") {
			$event->setKickMessage ( "Steve 닉네임은 사용할 수 없습니다 !" );
			$event->setCancelled ();
		}
	}
}
?>

<?php

namespace ifteam\InfiniteBlock;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use ifteam\SimpleArea\Event\AreaModifyEvent;

class OutEventListner implements Listener {
	public $plugin;
	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		if ($plugin->getServer ()->getPluginManager ()->getPlugin ( "SimpleArea" ) != null) {
			$plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		}
	}
	public function checkSimpleArea(AreaModifyEvent $event) {
		$area = $this->plugin->getArea ( $event->getBlock ()->x, $event->getBlock ()->y, $event->getBlock ()->z );
		if ($area != false) $event->setCancelled ();
	}
}
?>
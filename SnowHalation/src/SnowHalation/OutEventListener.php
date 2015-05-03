<?php

namespace SnowHalation;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use SimpleArea\Event\AreaModifyEvent;
use pocketmine\block\Block;

class OutEventListener implements Listener {
	public $plugin;
	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		if ($plugin->getServer ()->getPluginManager ()->getPlugin ( "SimpleArea" ) != null) {
			$plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		}
	}
	public function checkSimpleArea(AreaModifyEvent $event) {
		if ($event->getBlock ()->getId () == Block::SNOW_LAYER) $event->setCancelled ();
	}
}
?>
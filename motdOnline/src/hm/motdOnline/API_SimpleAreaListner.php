<?php

namespace examplePlugin;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;

class API_SimpleAreaListner implements Listener {
	public $plugin;
	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		if ($plugin->getServer ()->getPluginManager ()->getPlugin ( "SimpleArea" ) != null) {
			$plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		}
	}
	public function checkSimpleArea(\SimpleArea\Event\AreaModifyEvent $event) {}
}
?>
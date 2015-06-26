<?php

namespace ifteam\LoadBalancer\api;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use ifteam\EDGE\EDGE;
use ifteam\LoadBalancer\task\EDGEControlTask;

class EDGEControl implements Listener {
	public $plugin; /* LoadBalancer */
	public $callback; /* TickTask */
	public $edge; /* EDGE */
	public $beforeLine = null; /* before EDGE SpecialLine */
	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		if ($plugin->getServer ()->getPluginManager ()->getPlugin ( "EDGE" ) != null) {
			$plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
			// $this->edge = EDGE::getInstance ();
			$this->edge = $plugin->getServer ()->getPluginManager ()->getPlugin ( "EDGE" );
			$this->callback = $this->plugin->getServer ()->getScheduler ()->scheduleRepeatingTask ( new EDGEControlTask ( $this ), 20 );
		}
	}
	public function tick() {
		/* Instance Check */
		if (! $this->edge instanceof EDGE)
			return;
		
		/* D before Special Line */
		if ($this->beforeLine != null)
			$this->edge->deleteSpecialLine ( null, $this->beforeLine );
		
		if (! isset ( $this->plugin->slaveData ["online"] ) or ! isset ( $this->plugin->slaveData ["max"] ))
			return;
		
		$count = $this->plugin->slaveData ["online"] . "/" . $this->plugin->slaveData ["max"];
		$this->beforeLine = $this->plugin->get ( "all-online-count" ) . " " . $count;
		
		$this->edge->addSpecialLine ( null, $this->beforeLine );
	}
}

?>
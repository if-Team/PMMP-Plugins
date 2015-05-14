<?php

namespace ifteam\LoadBalancer\task;

use pocketmine\scheduler\PluginTask;
use ifteam\LoadBalancer\LoadBalancer;

class LoadBalancerTask extends PluginTask {
	public $owner;
	public function __construct(LoadBalancer $owner) {
		$this->owner = $owner;
	}
	public function onRun($currentTick) {
		$this->owner->tick ();
	}
}

?>
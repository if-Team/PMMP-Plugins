<?php

namespace ifteam\LoadBalancer\task;

use pocketmine\scheduler\Task;
use ifteam\LoadBalancer\api\EDGEControl;

class EDGEControlTask extends Task {
	public $owner;
	public function __construct(EDGEControl $owner) {
		$this->owner = $owner;
	}
	public function onRun($currentTick) {
		$this->owner->tick ();
	}
}

?>
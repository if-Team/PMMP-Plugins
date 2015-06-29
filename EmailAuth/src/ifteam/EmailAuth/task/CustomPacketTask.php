<?php

namespace ifteam\EmailAuth\task;

use ifteam\EmailAuth\EmailAuth;
use pocketmine\scheduler\Task;
use ifteam\EmailAuth\api\API_CustomPacketListner;

class CustomPacketTask extends Task {
	public $owner;
	function __construct(API_CustomPacketListner $owner) {
		$this->owner = $owner;
	}
	public function onRun($currentTick) {
		$this->owner->tick ();
	}
}

?>
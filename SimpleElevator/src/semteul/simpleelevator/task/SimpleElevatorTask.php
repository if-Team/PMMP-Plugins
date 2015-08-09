<?php

namespace semteul\simpleelevator\task;

use semteul\simpleelevator\SimpleElevator;

use pocketmine\scheduler\PluginTask;

class SimpleElevatorTask extends PluginTask {
	function __construct(SimpleElevator $owner) {
		parent::__construct($owner);
	}
	public function onRun($currentTick) {
		$owner = $this->getOwner();
		$owner->ElevatorTickTask();
	}
}
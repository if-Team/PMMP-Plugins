<?php

namespace codeinside\simpleelevator\task;

use pocketmine\scheduler\PluginTask;
use codeinside\simpleelevator\SimpleElevator;

class SimpleElevatorTask extends PluginTask {
	function __construct(SimpleElevator $owner) {
		parent::__construct($owner);
	}
	public function onRun($currentTick) {
		$owner = $this->getOwner();
		$owner->ElevatorTickTask();
	}
}
<?php

namespace ifteam\ZombieInfection;

use pocketmine\scheduler\PluginTask;

class InfectionScheduleTask extends PluginTask {
	function __construct(ZombieInfection $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		$owner = $this->getOwner ();
		$owner->infectionSchedule ();
	}
}
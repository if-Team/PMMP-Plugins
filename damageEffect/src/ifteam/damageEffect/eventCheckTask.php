<?php

namespace ifteam\damageEffect;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Level;

class eventCheckTask extends PluginTask {
	public $owner, $particle, $level, $event;
	public function __construct(damageEffect $owner, $particle, Level $level, $event) {
		$this->owner = $owner;
		$this->particle = $particle;
		$this->level = $level;
		$this->event = $event;
	}
	public function onRun($currentTick) {
		$this->owner->eventCheck ( $this->particle, $this->level , $this->event);
	}
}

?>
<?php

namespace ifteam\damageEffect;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Level;

class EventCheckTask extends PluginTask {
	public $particle, $level, $event;
	public function __construct(damageEffect $owner, $particle, Level $level, $event) {
        parent::__construct($owner);

        $this->particle = $particle;
		$this->level = $level;
		$this->event = $event;
	}
	public function onRun($currentTick) {
        /** @var $owner DamageEffect */
        $owner = $this->getOwner();
		$owner->eventCheck ( $this->particle, $this->level , $this->event);
	}
}

?>
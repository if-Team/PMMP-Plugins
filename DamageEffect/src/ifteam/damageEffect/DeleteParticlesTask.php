<?php

namespace ifteam\damageEffect;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Level;

class DeleteParticlesTask extends PluginTask {
	public $particle, $level;

	public function __construct(damageEffect $owner, $particle, Level $level) {
		parent::__construct($owner);

		$this->particle = $particle;
		$this->level = $level;
	}

	public function onRun($currentTick) {
        /** @var $owner DamageEffect */
        $owner = $this->getOwner();
        $owner->deleteParticles ( $this->particle, $this->level );
	}
}

?>
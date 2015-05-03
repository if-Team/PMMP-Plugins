<?php

namespace ifteam\SnowHalation;

use pocketmine\scheduler\PluginTask;

class SnowHalationTask extends PluginTask {
	function __construct(SnowHalation $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner SnowHalation
		 */
		$owner = $this->getOwner ();
		$owner->SnowHalation ();
	}
}
?>
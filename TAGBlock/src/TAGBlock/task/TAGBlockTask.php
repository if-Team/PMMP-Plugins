<?php

namespace TAGBlock\task;

use pocketmine\scheduler\PluginTask;
use TAGBlock\TAGBlock;

class TAGBlockTask extends PluginTask {
	public function __construct(TAGBlock $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		$this->owner->TAGBlock ();
	}
}

?>
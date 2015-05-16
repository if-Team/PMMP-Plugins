<?php

namespace ifteam\Sparklers;

use pocketmine\scheduler\PluginTask;

class SparklersTask extends PluginTask {
	public $owner;
	public function __construct(Sparklers $owner) {
		$this->owner = $owner;
	}
	public function onRun($currentTick) {
		$this->owner->fire ();
	}
}

?>
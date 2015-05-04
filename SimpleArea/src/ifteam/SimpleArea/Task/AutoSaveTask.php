<?php

namespace ifteam\SimpleArea\Task;

use pocketmine\scheduler\PluginTask;
use ifteam\SimpleArea\SimpleArea;

class AutoSaveTask extends PluginTask {
	public function __construct(SimpleArea $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		$this->owner->autoSave ();
	}
}

?>
<?php

namespace ifteam\Trampoline\task;

use pocketmine\scheduler\PluginTask;
use ifteam\Trampoline\Trampoline;

class fallenTimeOutTask extends PluginTask {
	public $name;
	public function __construct(Trampoline $owner, $name) {
		parent::__construct ( $owner );
		$this->name = $name;
	}
	public function onRun($currentTick) {
		$this->owner->fallenTimeOut ( $this->name );
	}
}

?>
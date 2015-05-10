<?php

namespace ifteam\CreativeEconomy\task;

use pocketmine\scheduler\PluginTask;
use CreativeEconomy\CreativeEconomy;

class CreativeEconomyTask extends PluginTask {
	public function __construct(CreativeEconomy $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		$this->owner->CreativeEconomy ();
	}
}

?>
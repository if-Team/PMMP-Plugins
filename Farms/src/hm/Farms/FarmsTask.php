<?php

namespace hm\Farms;

use pocketmine\scheduler\PluginTask;

class FarmsTask extends PluginTask {
	public function __construct(Farms $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner FarmsTask
		 */
		$owner = $this->getOwner ();
		$owner->Farms ();
	}
}

?>
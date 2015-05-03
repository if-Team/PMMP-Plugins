<?php

namespace ifteam\SnowHalation;

use pocketmine\scheduler\PluginTask;
use pocketmine\level\Position;

class createSnowLayerTask extends PluginTask {
	public $position;
	function __construct(SnowHalation $owner, Position $position) {
		parent::__construct ( $owner );
		$this->position = $position;
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner SnowHalation
		 */
		$owner = $this->getOwner ();
		$owner->createSnowLayer ( $this->position );
	}
}
?>
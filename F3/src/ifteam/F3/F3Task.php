<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:27
 */
namespace ifteam\F3;

use pocketmine\scheduler\PluginTask;

class F3Task extends PluginTask {
	function __construct(F3 $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner F3
		 */
		$owner = $this->getOwner ();
		$owner->tick ();
	}
}
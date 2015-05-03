<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:23
 */
namespace ifteam\autoReseter;

use pocketmine\scheduler\PluginTask;

class ResetTask extends PluginTask {
	function __construct(autoReseter $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner autoReseter
		 */
		$owner = $this->getOwner ();
		$owner->Reset ();
	}
}
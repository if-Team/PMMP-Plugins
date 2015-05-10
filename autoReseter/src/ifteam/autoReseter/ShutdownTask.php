<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:26
 */
namespace ifteam\autoReseter;

use pocketmine\scheduler\PluginTask;

class ShutdownTask extends PluginTask {
	function __construct(autoReseter $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner autoReseter
		 */
		$owner = $this->getOwner ();
		$owner->Shutdown ();
	}
}
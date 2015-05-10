<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:14
 */
namespace ifteam\Purge;

use pocketmine\scheduler\PluginTask;

class PurgeScheduleTask extends PluginTask {
	function __construct(Purge $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner Purge
		 */
		$owner = $this->getOwner ();
		$owner->purgeSchedule ();
	}
}
<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 17:37
 */
namespace ifteam\AnnouncePro;

use pocketmine\scheduler\PluginTask;

class AnnounceProTask extends PluginTask {
	function __construct(AnnouncePro $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner AnnouncePro
		 */
		$owner = $this->getOwner ();
		$owner->AnnouncePro ();
	}
}
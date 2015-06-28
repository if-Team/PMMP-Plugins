<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:56
 */
namespace ifteam\EmailAuth\task;

use pocketmine\scheduler\PluginTask;
use ifteam\EmailAuth\EmailAuth;

class AutoSaveTask extends PluginTask {
	function __construct(EmailAuth $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner EmailAuth
		 */
		$owner = $this->getOwner ();
		$owner->autoSave ();
	}
}
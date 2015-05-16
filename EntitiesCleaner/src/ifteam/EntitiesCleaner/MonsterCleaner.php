<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:58
 */
namespace ifteam\EntitiesCleaner;

use pocketmine\scheduler\PluginTask;

class MonsterCleaner extends PluginTask {
	function __construct(EntitiesCleaner $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner EntitiesCleaner
		 */
		$owner = $this->getOwner ();
		$owner->onMonsterClean ();
	}
}
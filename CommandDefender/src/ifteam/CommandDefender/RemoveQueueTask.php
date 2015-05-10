<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:51
 */
namespace ifteam\CommandDefender;

use pocketmine\scheduler\PluginTask;

class RemoveQueueTask extends PluginTask {
	function __construct(CommandDefender $owner) {
		parent::__construct ( $owner );
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner CommandDefender
		 */
		$owner = $this->getOwner ();
		$owner->removeQueue ();
	}
}
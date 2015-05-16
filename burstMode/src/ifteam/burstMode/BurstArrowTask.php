<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:47
 */
namespace ifteam\burstMode;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class BurstArrowTask extends PluginTask {
	/**
	 * @var Player
	 */
	private $player = null;
	function __construct(burstMode $owner, $player) {
		parent::__construct ( $owner );
		$this->player = $player;
	}
	public function onRun($currentTick) {
		/**
		 * @var $owner burstMode
		 */
		$owner = $this->getOwner ();
		$owner->burstArrow ( $this->player );
	}
}
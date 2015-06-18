<?php

namespace ifteam\CommandDefender\task;

use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use ifteam\CommandDefender\CommandDefender;

class PlayerCommandPreprocessEventTask extends PluginTask {
	public $event;
	public function __construct(CommandDefender $owner, PlayerCommandPreprocessEvent $event) {
		parent::__construct ( $owner );
		$this->event = $event;
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner CommandDefender
		 */
		$owner = $this->getOwner ();
		$owner->commandCheck ( $this->event );
	}
}
?>
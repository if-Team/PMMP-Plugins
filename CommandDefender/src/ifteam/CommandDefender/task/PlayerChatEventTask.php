<?php

namespace ifteam\CommandDefender\task;

use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerChatEvent;
use ifteam\CommandDefender\CommandDefender;

class PlayerChatEventTask extends PluginTask {
	public $event;
	public function __construct(CommandDefender $owner, PlayerChatEvent $event) {
		parent::__construct ( $owner );
		$this->event = $event;
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner CommandDefender
		 */
		$owner = $this->getOwner ();
		$owner->chatCheck ( $this->event );
	}
}
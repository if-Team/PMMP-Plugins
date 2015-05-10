<?php

namespace ifteam\EchoChat;

use pocketmine\scheduler\PluginTask;
use pocketmine\event\Event;

class EchoChatTask extends PluginTask {
	public $event;
	public function __construct(EchoChat $owner, Event $event) {
		parent::__construct ( $owner );
		$this->event = $event;
	}
	public function onRun($currentTick) {
		/**
		 *
		 * @var $owner EDGE
		 */
		$owner = $this->getOwner ();
		$owner->sendPacket ( $this->event );
	}
}

?>
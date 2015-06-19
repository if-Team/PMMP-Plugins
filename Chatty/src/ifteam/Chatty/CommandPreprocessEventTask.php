<?php

namespace ifteam\Chatty;

use pocketmine\scheduler\PluginTask;

class CommandPreprocessEventTask extends PluginTask {
	public $owner;
	public $event;
	public $message;
	function __construct(Chatty $owner, $event, $message){
		$this->owner = $owner;
		$this->event = $event;
		$this->message = $message;
	}
	public function onRun($currentTick){
		$this->owner->custompacketAPI->sendRedistribution($this->event, $this->message);
	}
}

?>
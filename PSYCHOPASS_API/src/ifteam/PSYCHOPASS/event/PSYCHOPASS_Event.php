<?php

namespace ifteam\PSYCHOPASS\Event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;
use ifteam\PSYCHOPASS;

class PSYCHOPASS_API_Event extends PluginEvent implements Cancellable {
	public function __construct(PSYCHOPASS_API $plugin, $issuer) {
		$this->plugin = $plugin;
	}
	public function getIssuer() {
		return $this->issuer;
	}
}
?>
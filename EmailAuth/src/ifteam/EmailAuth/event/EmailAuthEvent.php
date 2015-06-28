<?php

namespace ifteam\EmailAuth\event;

use pocketmine\event\plugin\PluginEvent;
use ifteam\EmailAuth\EmailAuth;

abstract class EmailAuthEvent extends PluginEvent {
	/**
	 *
	 * @param EmailAuth $plugin        	
	 */
	public function __construct(EmailAuth $plugin) {
		parent::__construct ( $plugin );
	}
}

?>
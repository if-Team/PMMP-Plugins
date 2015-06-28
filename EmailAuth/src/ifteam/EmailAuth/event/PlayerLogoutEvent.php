<?php

namespace ifteam\EmailAuth\event;

use ifteam\EmailAuth\EmailAuth;
use pocketmine\IPlayer;

class PlayerLogoutEvent extends EmailAuthEvent {
	public static $handlerList = null;
	
	/**
	 *
	 * @var IPlayer
	 */
	private $player;
	
	/**
	 *
	 * @param EmailAuth $plugin        	
	 * @param IPlayer $player        	
	 */
	public function __construct(EmailAuth $plugin, IPlayer $player) {
		$this->player = $player;
		parent::__construct ( $plugin );
	}
	
	/**
	 *
	 * @return IPlayer
	 */
	public function getPlayer() {
		return $this->player;
	}
}

?>
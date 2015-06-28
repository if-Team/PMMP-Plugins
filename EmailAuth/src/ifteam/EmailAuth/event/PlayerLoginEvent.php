<?php

namespace ifteam\EmailAuth\event;

use pocketmine\event\Cancellable;
use ifteam\EmailAuth\EmailAuth;
use pocketmine\Player;

class PlayerLoginEvent extends EmailAuthEvent implements Cancellable {
	public static $handlerList = null;
	
	/**
	 *
	 * @var Player
	 */
	private $player;
	
	/**
	 *
	 * @param EmailAuth $plugin        	
	 * @param IPlayer $player        	
	 */
	public function __construct(EmailAuth $plugin, Player $player) {
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
<?php

namespace ifteam\EmailAuth\event;

use ifteam\EmailAuth\EmailAuth;
use pocketmine\event\Cancellable;
use pocketmine\Player;

class PlayerRegisterEvent extends EmailAuthEvent implements Cancellable {
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
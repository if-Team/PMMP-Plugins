<?php

namespace EDGE;

use pocketmine\plugin\PluginBase;
use pocketmine\network\protocol\MessagePacket;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;

class EDGE extends PluginBase {
	public $packet;
	public $economyAPI = null;
	public function onEnable() {
		$this->packet = new MessagePacket ();
		if ($this->checkEconomyAPI ()) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->alert ( "이코노미가 없습니다 ! 작동 불가능!" );
		}
		$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"EDGE" 
		] ), 20 * 10 );
	}
	public function EDGE() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$message = "보유금액 " . $this->economyAPI->myMoney ( $player->getName () ) . "$";
			$this->packet->message = "\n\n\n\n\n\n\n\n\n                                            " . $message;
			$player->directDataPacket ( $this->packet );
		}
	}
	public function checkEconomyAPI() {
		return (($this->getServer ()->getLoader ()->findClass ( 'onebone\\economyapi\\EconomyAPI' )) == null) ? false : true;
	}
}
?>
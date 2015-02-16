<?php

namespace hm\SteveWarn;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\protocol\ChatPacket;
use pocketmine\scheduler\CallbackTask;

class SteveWarn extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function PlayerJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		if ($player->getName () == "Steve" or $player->getName () == "steve") {
			$pk = new ChatPacket ();
			$pk->message = "[경고] 닉네임이 Steve입니다, 해당닉네임은 사용불가능합니다\n[경고] 자동으로 킥처리되며 닉네임 변경시 정상이용가능합니다";
			$player->dataPacket ( $pk );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"Kick"
			], [$player]), 80 );
		}
	}
	public function Kick($player){
		$player->kick("기본닉네임");
	}
}
?>
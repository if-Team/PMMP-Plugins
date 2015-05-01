<?php

namespace hm\motdOnline;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

class motdOnline extends PluginBase implements Listener {
	public $m_version = 1; // 현재 메시지 버전
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new UpdateServerNameTask ( $this ), 20 );
	}
	public function updateServerName() {
		$motd = $this->getServer ()->getConfigString ( "motd", "Minecraft: PE Server" );
		$this->getServer ()->getNetwork ()->setName ( $motd . " (" . count ( $this->getServer ()->getOnlinePlayers () ) . "/" . $this->getServer ()->getMaxPlayers () . ")" );
	}
}

?>
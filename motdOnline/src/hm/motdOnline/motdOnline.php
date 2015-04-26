<?php

namespace hm\motdOnline;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;

class motdOnline extends PluginBase implements Listener {
	public $m_version = 1; // 현재 메시지 버전
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"updateServerName" ] ), 20 );
	}
	public function updateServerName() {
		$reflection_class = new \ReflectionClass ( "\\pocketmine\\Server" );
		$property = $reflection_class->getProperty ( 'network' );
		$property->setAccessible ( true );
		$network = $property->getValue ( $this->getServer () );
		$motd = $this->getServer ()->getConfigString ( "motd", "Minecraft: PE Server" );
		$network->setName ( $motd . " (" . count ( $this->getServer ()->getOnlinePlayers () ) . " / " . $this->getServer ()->getMaxPlayers () . ")" );
	}
	// ----------------------------------------------------------------------------------
}

?>
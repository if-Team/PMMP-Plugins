<?php

/**  __    __       __    __    
 * /＼ ＼_＼ ＼   /＼  "-./ ＼   
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼  
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼ 
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/ 
 * ( *you can redistribute it and/or modify *) */
namespace hm\autoReseter;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\Config;

class autoReseter extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->resetTimer = (new Config ( $this->getDataFolder () . "resetTimer.yml", Config::YAML, [ 
				"resetCycle" => 36000 
		] ))->getAll ();
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$this,
				"Reset" 
		] ), $this->resetTimer ["resetCycle"] );
	}
	/**
	 *
	 * @var autoReset Notification
	 */
	public function Reset() {
		$this->getServer ()->broadcastMessage ( TextFormat::DARK_PURPLE . "[안내] 서버가 10초뒤 5~10초간 재부팅됩니다 *자동재부팅*" );
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$this,
				"Shutdown" 
		] ), 20 * 10 );
	}
	/**
	 *
	 * @var execute autoReset
	 */
	public function Shutdown() {
		$this->getServer ()->broadcastMessage ( TextFormat::DARK_PURPLE . "[안내] 서버가 재부팅됩니다.." );
		/**
		 *
		 * @var entitiesSave
		 */
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$player->save ();
		}
		foreach ( $this->getServer ()->getLevels () as $level ) {
			$level->save ( \true );
		}
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$player->kick ( "서버가 곧 재부팅됩니다" );
		}
		$this->getServer ()->shutdown ();
	}
}
?>
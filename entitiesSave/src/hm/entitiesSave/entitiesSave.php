<?php

namespace hm\entitiesSave;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;

class entitiesSave extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function UserCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		
		if (! $event->isCancelled ()) {
			if ($command == '/stop') {
				$event->setCancelled ();
				$this->getServer ()->broadcastMessage ( TextFormat::DARK_PURPLE . "[안내] 서버가 5초 뒤 재부팅됩니다 *stop 명령어 작동" );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"entitiesSave" 
				] ), 20 * 5 );
			}
		}
	}
	public function ServerCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		
		if ($event->isCancelled ())
			return false;
		if ($command == 'stop') {
			$event->setCancelled ();
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_PURPLE . "[안내] 서버가 5초 뒤 재부팅됩니다 *stop 명령어 작동" );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"entitiesSave" 
			] ), 20 * 5 );
		}
	}
	public function entitiesSave() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$player->save ();
		}
		
		foreach ( $this->getServer ()->getLevels () as $level ) {
			$level->save ( \true );
		}
		$this->getServer ()->shutdown ();
	}
}
?>
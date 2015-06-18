<?php

namespace ifteam\HungerGames\task;

use pocketmine\scheduler\Task;
use pocketmine\event\Event;
use pocketmine\level\Explosion;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\Server;
use pocketmine\entity\Arrow;
use pocketmine\level\particle\MobSpawnParticle;
use pocketmine\Player;

class removeArrowTask extends Task {
	public $event, $server;
	public function __construct(Event $event, Server $server) {
		$this->event = $event;
		$this->server = $server;
	}
	public function onRun($currentTick) {
		if ($this->event->isCancelled ()) return;
		
		$arrow = $this->event->getEntity ();
		$murder = $this->event->getEntity ()->shootingEntity;
		
		if (! $arrow instanceof Arrow) return;
		if(! $murder instanceof Player) return;
		$this->server->getPluginManager ()->callEvent ( $ev = new ExplosionPrimeEvent ( $arrow, 3.2 ) );
		if (! $ev->isCancelled ()) {
			$arrow->getLevel ()->addParticle ( new MobSpawnParticle ( $arrow, 2, 2 ) );
			$explosion = new Explosion ( $arrow, $ev->getForce (), $murder );
			$explosion->explodeB ();
		}
		
		$reflection_class = new \ReflectionClass ( $arrow );
		$property = $reflection_class->getProperty ( 'age' );
		$property->setAccessible ( true );
		$property->setValue ( $arrow, 7000 );
	}
}

?>
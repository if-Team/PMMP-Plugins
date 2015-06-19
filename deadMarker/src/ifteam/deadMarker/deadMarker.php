<?php

namespace ifteam\deadMarker;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\event\player\PlayerRespawnEvent;

class deadMarker extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	/**
	 * @priority high
	 */
	public function onDeath(PlayerRespawnEvent $event) {
		$level = $event->getPlayer ()->getLevel ();
		$pillarPos = new Position ( $event->getPlayer ()->x, $event->getPlayer ()->y, $event->getPlayer ()->z, $level );
		for($h = 1; $h <= 30; $h ++) {
			$pillarPos->setComponents ( $pillarPos->x, ++ $pillarPos->y, $pillarPos->z );
			$level->addParticle ( new RedstoneParticle ( $pillarPos, 10 ) );
		}
		$pillarPos->setComponents ( $pillarPos->x, $pillarPos->y - 10, $pillarPos->z );
		$headPos = new Position ( $pillarPos->x, $pillarPos->y, $pillarPos->z, $level );
		for($r = - 5; $r <= 5; $r ++) {
			$headPos->setComponents ( $pillarPos->x + $r, $pillarPos->y, $pillarPos->z );
			$level->addParticle ( new ExplodeParticle ( $headPos ) );
			$p = new RedstoneParticle ( $headPos, 10 );
			$level->addParticle ( $p );
		}
	}
}

?>
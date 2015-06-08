<?php

namespace ifteam\damageEffect;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Entity;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;

class DamageEffect extends PluginBase implements Listener {
	public function onEnable() {
		// 서버이벤트를 받아오게끔 플러그인 리스너를 서버에 등록
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}

	public function onDamage(EntityDamageEvent $event) {
		if (! $event->getEntity () instanceof Entity) return;
		
		if ($event->getDamage () < 3) {
			$color = TextFormat::GREEN;
		} else if ($event->getDamage () < 6) {
			$color = TextFormat::YELLOW;
		} else {
			$color = TextFormat::RED;
		}
		
		$pos = $event->getEntity ()->add ( 0.1 * mt_rand ( 1, 9 ) * mt_rand ( - 1, 1 ), 0.1 * mt_rand ( 5, 9 ), 0.1 * mt_rand ( 1, 9 ) * mt_rand ( - 1, 1 ) );
		$damageParticle = new FloatingTextParticle ( $pos, "", $color . "-" . $event->getDamage () );
		
		if ($event->getEntity ()->getHealth () < 7) {
			$color = TextFormat::RED;
		} else if ($event->getEntity ()->getHealth () < 14) {
			$color = TextFormat::YELLOW;
		} else {
			$color = TextFormat::GREEN;
		}
		
		$pos = $event->getEntity ()->add ( 0, 2.5, 0 );
		$healthParticle = new FloatingTextParticle ( $pos, "", $color . ($event->getEntity ()->getHealth () - $event->getDamage ()) . " / " . $event->getEntity ()->getMaxHealth () );
		
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new EventCheckTask ( $this, $damageParticle, $event->getEntity ()->getLevel (), $event ), 1 );
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new EventCheckTask ( $this, $healthParticle, $event->getEntity ()->getLevel (), $event ), 1 );
	}

	public function eventCheck(FloatingTextParticle $particle, Level $level, $event) {
		if ($event instanceof EntityDamageEvent) if ($event->isCancelled ()) return;
		$level->addParticle ( $particle );
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new DeleteParticlesTask ( $this, $particle, $event->getEntity ()->getLevel () ), 20 );
	}

	public function deleteParticles(FloatingTextParticle $particle, Level $level) {
		$particle->setInvisible ();
		$level->addParticle ( $particle );
	}
}

?>
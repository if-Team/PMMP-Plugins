<?php

namespace StartDASH;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\entity\EntityDamageEvent;

class StartDASH extends PluginBase implements Listener {
	public $past, $past_q;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		$this->past [$player->getName ()] = 0;
		$this->past_q [$player->getName ()] = $player->yaw . ":" . $player->pitch;
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->past [$player->getName ()] ))
			unset ( $this->past [$player->getName ()] );
		if (isset ( $this->past_q [$player->getName ()] ))
			unset ( $this->past_q [$player->getName ()] );
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		if ($this->past_q [$player->getName ()] != $player->yaw . ":" . $player->pitch) {
			$this->past_q [$player->getName ()] = $player->yaw . ":" . $player->pitch;
			return;
		}
		$this->checkMove ( $player, round ( microtime ( true ) * 1000 ) );
		$this->past_q [$player->getName ()] = $player->yaw . ":" . $player->pitch;
	}
	public function checkMove(Player &$player, $time) {
		if (($time - $this->past [$player->getName ()]) > 400) { // stable->450 safe->600
			$x = - \sin ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI );
			$y = - \sin ( $player->pitch / 180 * M_PI );
			$z =\cos ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI );
			$player->addEntityMotion ( 0, $x, $y, $z );
			// echo "c! " . ($time - $this->past [$player->getName ()]) . "\n";
			// echo "n! " . $x . ":" . $y . ":" . $z . "\n";
		}
		$this->past [$player->getName ()] = $time;
	}
	public function fallenDamagePrevent(EntityDamageEvent $event) {
		if ($event->getCause () == EntityDamageEvent::CAUSE_FALL) {
			if (! $event->getEntity () instanceof Player)
				return;
			if ($event->getEntity ()->y > 0) {
				$event->setDamage ( 0 );
				$event->setCancelled ();
			}
		}
	}
}
?>

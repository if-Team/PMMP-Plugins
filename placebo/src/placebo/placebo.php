<?php

namespace placebo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\Player;

class placebo extends PluginBase implements Listener {
	public $move = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onPlace(BlockPlaceEvent $event) {
		if ($event->getBlock ()->distance ( $event->getPlayer () ) > 7)
			$event->setCancelled ();
	}
	public function onBreak(BlockBreakEvent $event) {
		if ($event->getBlock ()->distance ( $event->getPlayer () ) > 7)
			$event->setCancelled ();
	}
	public function onActivate(PlayerInteractEvent $event) {
		if ($event->getBlock ()->distance ( $event->getPlayer () ) > 7)
			$event->setCancelled ();
	}
	public function onAttack(EntityDamageEvent $event) {
		if (! $event instanceof EntityDamageByEntityEvent)
			return;
		if ($event->getEntity () instanceof Player and $event->getDamager () instanceof Player)
			if ($event->getEntity ()->distance ( $event->getDamager () ) > 7)
				$event->setCancelled ();
	}
	public function onMove(PlayerMoveEvent $event) {
		if (isset ( $this->move [$event->getPlayer ()->getName ()] )) {
			unset ( $this->move [$event->getPlayer ()->getName ()] );
			return;
		}
		if ($event->getFrom ()->distance ( $event->getTo () ) > 7)
			$event->setCancelled ();
	}
	public function onTeleport(EntityTeleportEvent $event) {
		if (! $event->getEntity () instanceof Player)
			return;
		$this->move [$event->getEntity ()->getName ()] = null;
	}
}

?>
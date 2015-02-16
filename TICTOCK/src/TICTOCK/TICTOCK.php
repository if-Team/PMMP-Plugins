<?php

namespace TICTOCK;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class TICTOCK extends PluginBase implements Listener {
	public $tictock = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer ();
		$time = round ( microtime ( true ) * 1000 );
		
		if (($time - $this->tictock [$player->getName ()]) <= 450) {
			$event->setCancelled ();
		}
		$this->tictock [$player->getName ()] = $time;
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->tictock [$event->getPlayer ()->getName ()] = round ( microtime ( true ) * 1000 );
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->tictock [$player->getName ()] ))
			unset ( $this->tictock [$player->getName ()] );
	}
}

?>
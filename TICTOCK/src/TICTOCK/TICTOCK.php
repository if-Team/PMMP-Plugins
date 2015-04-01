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
	public $kick = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer ();
		
		if ($player->isCreative ()) return;
		$time = round ( microtime ( true ) * 1000 );
		
		if ($event->getBlock ()->getBreakTime ( $event->getItem () ) <= 0.2) return;
		
		if (($time - $this->tictock [$player->getName ()]) <= 200) {
			if (! isset ( $this->kick [$player->getName ()] )) {
				$this->kick [$player->getName ()] = 1;
			} else {
				$this->kick [$player->getName ()] ++;
			}
			if ($this->kick > 2) {
				$player->kick ( "파괴자모드 감지" );
				if (isset ( $this->kick [$player->getName ()] )) unset ( $this->kick [$player->getName ()] );
			}
			$event->setCancelled ();
		} else {
			if (isset ( $this->kick [$player->getName ()] )) unset ( $this->kick [$player->getName ()] );
		}
		$this->tictock [$player->getName ()] = $time;
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->tictock [$event->getPlayer ()->getName ()] = round ( microtime ( true ) * 1000 );
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->tictock [$player->getName ()] )) unset ( $this->tictock [$player->getName ()] );
	}
}

?>
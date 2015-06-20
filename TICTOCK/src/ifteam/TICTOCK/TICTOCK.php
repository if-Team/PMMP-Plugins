<?php

namespace ifteam\TICTOCK;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
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
		
		if ($player->isCreative ())
			return;
		if ($event->getBlock ()->getBreakTime ( $event->getItem () ) <= 0.1)
			return;
		
		$time = round ( microtime ( true ) * 1000 );
		
		if (! isset ( $this->tictock [spl_object_hash ( $player )] )) {
			$this->tictock [spl_object_hash ( $player )] = $time;
			return;
		}
		
		if (($time - $this->tictock [spl_object_hash ( $player )]) <= 60) {
			if (! isset ( $this->kick [spl_object_hash ( $player)] )) {
				$this->kick [spl_object_hash ( $player )] = 1;
			} else {
				$this->kick [spl_object_hash ( $player )] ++;
				if ($this->kick [spl_object_hash ( $player )] > 2) {
					$player->kick ( "파괴자모드 감지" );
					unset ( $this->kick [spl_object_hash ( $player )] );
				}
			}
			$event->setCancelled ();
		} else {
			if (isset ( $this->kick [spl_object_hash ( $player )] ))
				unset ( $this->kick [spl_object_hash ( $player )] );
		}
		$this->tictock [spl_object_hash ( $player )] = $time;
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->tictock [spl_object_hash ( $event->getPlayer() )] = round ( microtime ( true ) * 1000 );
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		
		if (isset ( $this->tictock [spl_object_hash ( $player )] ))
			unset ( $this->tictock [spl_object_hash ( $player )] );
		
		if (isset ( $this->kick [spl_object_hash ( $player )] ))
			unset ( $this->kick [spl_object_hash ( $player )] );
	}
}

?>
<?php

namespace Trampoline;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageEvent;

class Trampoline extends PluginBase implements Listener {
	public $fallen = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		
		if ($player == null) return;
		if ($player->getLevel () == null) return;
		
		// under
		$x = ( int ) round ( $player->x - 0.5 );
		$y = ( int ) round ( $player->y - 1 );
		$z = ( int ) round ( $player->z - 0.5 );
		
		$id = $player->getLevel ()->getBlockIdAt ( $x, $y, $z );
		$data = $player->getLevel ()->getBlockDataAt ( $x, $y, $z );
		
		if ($id == 35 and $data == 5) {
			$this->fallenQueue ( $player );
			$player->addEntityMotion ( 0, 0, 10, 0 );
		} else if ($id == 35 and $data == 4) {
			$this->fallenQueue ( $player );
			$player->addEntityMotion ( 0, 0, 1, 0 );
		} else if ($id and $data == 10) {
			$this->fallenQueue ( $player );
			$player->addEntityMotion ( 0, 0, 20, 0 );
		} else {
			// right
			$x = ( int ) round ( $player->x - 1.5 );
			$y = ( int ) round ( $player->y );
			$z = ( int ) round ( $player->z - 0.5 );
			
			$id = $player->getLevel ()->getBlockIdAt ( $x, $y, $z );
			$data = $player->getLevel ()->getBlockDataAt ( $x, $y, $z );
			
			if ($id == 35 and $data == 5) {
				$this->fallenQueue ( $player );
				$player->addEntityMotion ( 0, + 10, 0, 0 );
			} else if ($id == 35 and $data == 4) {
				$this->fallenQueue ( $player );
				$player->addEntityMotion ( 0, + 1, 0, 0 );
			} else if ($id and $data == 10) {
				$this->fallenQueue ( $player );
				$player->addEntityMotion ( 0, + 20, 0, 0 );
			} else {
				// left
				$x = ( int ) round ( $player->x + 0.5 );
				$y = ( int ) round ( $player->y );
				$z = ( int ) round ( $player->z - 0.5 );
				
				$id = $player->getLevel ()->getBlockIdAt ( $x, $y, $z );
				$data = $player->getLevel ()->getBlockDataAt ( $x, $y, $z );
				
				if ($id == 35 and $data == 5) {
					$this->fallenQueue ( $player );
					$player->addEntityMotion ( 0, - 10, 0, 0 );
				} else if ($id == 35 and $data == 4) {
					$this->fallenQueue ( $player );
					$player->addEntityMotion ( 0, - 1, 0, 0 );
				} else if ($id and $data == 10) {
					$this->fallenQueue ( $player );
					$player->addEntityMotion ( 0, - 20, 0, 0 );
				} else {
					// north
					$x = ( int ) round ( $player->x - 0.5 );
					$y = ( int ) round ( $player->y );
					$z = ( int ) round ( $player->z - 1.5 );
					
					$id = $player->getLevel ()->getBlockIdAt ( $x, $y, $z );
					$data = $player->getLevel ()->getBlockDataAt ( $x, $y, $z );
					
					if ($id == 35 and $data == 5) {
						$this->fallenQueue ( $player );
						$player->addEntityMotion ( 0, 0, 0, + 10 );
					} else if ($id == 35 and $data == 4) {
						$this->fallenQueue ( $player );
						$player->addEntityMotion ( 0, 0, 0, + 1 );
					} else if ($id and $data == 10) {
						$this->fallenQueue ( $player );
						$player->addEntityMotion ( 0, 0, 0, + 20 );
					} else {
						// north
						$x = ( int ) round ( $player->x - 0.5 );
						$y = ( int ) round ( $player->y );
						$z = ( int ) round ( $player->z + 0.5 );
						
						$id = $player->getLevel ()->getBlockIdAt ( $x, $y, $z );
						$data = $player->getLevel ()->getBlockDataAt ( $x, $y, $z );
						
						if ($id == 35 and $data == 5) {
							$this->fallenQueue ( $player );
							$player->addEntityMotion ( 0, 0, 0, - 10 );
						} else if ($id == 35 and $data == 4) {
							$this->fallenQueue ( $player );
							$player->addEntityMotion ( 0, 0, 0, - 1 );
						} else if ($id and $data == 10) {
							$this->fallenQueue ( $player );
							$player->addEntityMotion ( 0, 0, 0, - 20 );
						}
					}
				}
			}
		}
	}
	public function check() {}
	public function fallenQueue(Player $player) {
		if ($player == null) return;
		if (isset ( $this->fallen [$player->getName ()] )) {
			$this->fallen [$player->getName ()] ++;
		} else {
			$this->fallen [$player->getName ()] = 1;
		}
	}
	public function fallenDamagePrevent(EntityDamageEvent $event) {
		if ($event->getCause () == EntityDamageEvent::CAUSE_FALL) {
			if (! $event->getEntity () instanceof Player) return;
			
			if (isset ( $this->fallen [$event->getEntity ()->getName ()] )) {
				$event->setDamage ( 0 );
				$this->fallen [$event->getEntity ()->getName ()] --;
				if ($this->fallen [$event->getEntity ()->getName ()] == 0) unset ( $this->fallen [$event->getEntity ()->getName ()] );
			}
		}
	}
}

?>
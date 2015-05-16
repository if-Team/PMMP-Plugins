<?php

namespace ifteam\EntitiesCleaner;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\tile\Tile;
use pocketmine\entity\Creature;

class EntitiesCleaner extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new Cleaner ( $this ), 20 * 60 * 2 );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new MonsterCleaner ( $this ), 20 * 15 );
	}
	public function onClean() {
		foreach ( $this->getServer ()->getLevels () as $level )
			foreach ( $level->getEntities () as $entity ) {
				if ($entity instanceof Tile) continue;
				if ($entity instanceof Creature) continue;
				$entity->close ();
			}
	}
	public function onMonsterClean() {
		foreach ( $this->getServer ()->getLevels () as $level )
			foreach ( $level->getEntities () as $entity )
				if ($entity instanceof Creature) {
					$check = 0;
					foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
						$mx = abs ( $player->x - $entity->x );
						$my = abs ( $player->y - $entity->y );
						$mz = abs ( $player->z - $entity->z );
						if ($mx <= 25 and $my <= 25 and $mz <= 25) $check ++;
					}
					if ($check == 0) $entity->close ();
				}
	}
}

?>
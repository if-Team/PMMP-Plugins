<?php

namespace ifteam\burstMode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\entity\Snowball;
use pocketmine\entity\Arrow;
use pocketmine\Player;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;

class burstMode extends PluginBase implements Listener { // spl_object_hash
	public $object_hash = [ ];
	public $touchQueue = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDrop(PlayerItemConsumeEvent $event) {
		if ($event->getItem () instanceof Arrow or $event->getItem () instanceof Snowball) {
			if ($event->getItem ()->shootingEntity == null)
				return;
			if (isset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] ))
				$event->setCancelled ();
		}
	}
	public function onClose(EntityDespawnEvent $event) {
		if (isset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] ))
			unset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] );
	}
	public function onTouch(PlayerInteractEvent $event) {
		$this->touchQueue [spl_object_hash ( $event->getPlayer () )] = $event->getTouchVector ();
	}
	public function onPlayerQuit(PlayerQuitEvent $event) {
		if (isset ( $this->touchQueue [spl_object_hash ( $event->getPlayer () )] ))
			unset ( $this->touchQueue [spl_object_hash ( $event->getPlayer () )] );
	}
	public function burstMode(ProjectileLaunchEvent $event) {
		$entity = $event->getEntity ();
		$player = $event->getEntity ()->shootingEntity;
		
		if (! $player instanceof Player)
			return;
		if ($player->closed) return;
		if ($entity instanceof Snowball) {
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new BurstSnowballTask ( $this, $player ), 10 );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new BurstSnowballTask ( $this, $player ), 20 );
		}
	}
	public function burstSnowball(Player $player) {
		if (! isset ( $this->touchQueue [spl_object_hash ( $player )] ))
			return;
		$aimPos = $this->touchQueue [spl_object_hash ( $player )];
		$nbt = new Compound ( "", [ 
				"Pos" => new Enum ( "Pos", [ 
						new Double ( "", $player->x ),
						new Double ( "", $player->y + $player->getEyeHeight () ),
						new Double ( "", $player->z ) 
				] ),
				"Motion" => new Enum ( "Motion", [ 
						new Double ( "", $aimPos->x ),
						new Double ( "", $aimPos->y ),
						new Double ( "", $aimPos->z ) 
				] ),
				"Rotation" => new Enum ( "Rotation", [ 
						new Float ( "", $player->yaw ),
						new Float ( "", $player->pitch ) 
				] ) 
		] );
		
		$f = 1.5;
		$snowball = Entity::createEntity ( "Snowball", $player->chunk, $nbt, $player );
		$snowball->setMotion ( $snowball->getMotion ()->multiply ( $f ) );
		
		if ($snowball instanceof Projectile) {
			$this->getServer ()->getPluginManager ()->callEvent ( $projectileEv = new ProjectileLaunchEvent ( $snowball ) );
			if ($projectileEv->isCancelled ()) {
				$snowball->kill ();
			} else {
				$this->object_hash [spl_object_hash ( $snowball )] = 1;
				$snowball->spawnToAll ();
			}
		} else {
			$this->object_hash [spl_object_hash ( $snowball )] = 1;
			$snowball->spawnToAll ();
		}
	}
	public function burstArrow(Player $player) {
		if ($player->getInventory ()->getItemInHand ()->getId () === Item::BOW) {
			$bow = $player->getInventory ()->getItemInHand ();
			$nbt = new Compound ( "", [ 
					"Pos" => new Enum ( "Pos", [ 
							new Double ( "", $player->x ),
							new Double ( "", $player->y + $player->getEyeHeight () ),
							new Double ( "", $player->z ) 
					] ),
					"Motion" => new Enum ( "Motion", [ 
							new Double ( "", - \sin ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI ) ),
							new Double ( "", - \sin ( $player->pitch / 180 * M_PI ) ),
							new Double ( "",\cos ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI ) ) 
					] ),
					"Rotation" => new Enum ( "Rotation", [ 
							new Float ( "", $player->yaw ),
							new Float ( "", $player->pitch ) 
					] ) 
			] );
			
			$ev = new EntityShootBowEvent ( $player, $bow, Entity::createEntity ( "Arrow", $player->chunk, $nbt, $player ), 1.5 );
			
			$this->getServer ()->getPluginManager ()->callEvent ( $ev );
			
			if ($ev->isCancelled ()) {
				$ev->getProjectile ()->kill ();
			} else {
				$ev->getProjectile ()->setMotion ( $ev->getProjectile ()->getMotion ()->multiply ( $ev->getForce () ) );
				if ($ev->getProjectile () instanceof Projectile) {
					$this->getServer ()->getPluginManager ()->callEvent ( $projectileEv = new ProjectileLaunchEvent ( $ev->getProjectile () ) );
					if ($projectileEv->isCancelled ()) {
						$ev->getProjectile ()->kill ();
					} else {
						$this->object_hash [spl_object_hash ( $ev->getProjectile () )] = 1;
						$ev->getProjectile ()->spawnToAll ();
					}
				} else {
					$this->object_hash [spl_object_hash ( $ev->getProjectile () )] = 1;
					$ev->getProjectile ()->spawnToAll ();
				}
			}
		}
	}
}

?>
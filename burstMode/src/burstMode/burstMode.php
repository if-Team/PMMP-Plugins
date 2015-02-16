<?php

namespace burstMode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\entity\Snowball;
use pocketmine\entity\Arrow;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\item\Item;

class burstMode extends PluginBase implements Listener { // spl_object_hash
	public $object_hash = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDrop(PlayerItemConsumeEvent $event) {
		if ($event->getItem () instanceof Arrow or $event->getItem () instanceof Snowball) {
			if ($event->getItem ()->shootingEntity == null) return;
			if (isset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] )) $event->setCancelled ();
		}
	}
	public function onClose(EntityDespawnEvent $event) {
		if (isset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] )) unset ( $this->object_hash [spl_object_hash ( $event->getEntity () )] );
	}
	public function burstMode(ProjectileLaunchEvent $event) {
		$entity = $event->getEntity ();
		$player = $event->getEntity ()->shootingEntity;
		
		if ($player == null) return;
		if ($entity instanceof Snowball) {
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"burstSnowball" ], [ 
					$player ] ), 10 );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"burstSnowball" ], [ 
					$player ] ), 20 );
		}
		if ($entity instanceof Arrow) {
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"burstArrow" ], [ 
					$player ] ), 10 );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"burstArrow" ], [ 
					$player ] ), 20 );
		}
	}
	public function burstSnowball(Player $player) {
		$nbt = new Compound ( "", [ 
				"Pos" => new Enum ( "Pos", [ 
						new Double ( "", $player->x ),
						new Double ( "", $player->y + $player->getEyeHeight () ),
						new Double ( "", $player->z ) ] ),
				"Motion" => new Enum ( "Motion", [ 
						new Double ( "", - \sin ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI ) ),
						new Double ( "", - \sin ( $player->pitch / 180 * M_PI ) ),
						new Double ( "",\cos ( $player->yaw / 180 * M_PI ) *\cos ( $player->pitch / 180 * M_PI ) ) ] ),
				"Rotation" => new Enum ( "Rotation", [ 
						new Float ( "", $player->yaw ),
						new Float ( "", $player->pitch ) ] ) ] );
		
		$f = 1.5;
		$snowball = Entity::createEntity ( "Snowball", $player->chunk, $nbt, $player );
		$snowball->setMotion ( $snowball->getMotion ()->multiply ( $f ) );
		
		if ($snowball instanceof Projectile) {
			$this->server->getPluginManager ()->callEvent ( $projectileEv = new ProjectileLaunchEvent ( $snowball ) );
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
							new Double ( "", $player->z ) ] ),
					"Motion" => new Enum ( "Motion", [ 
							new Double ( "", -\sin ( $player->yaw / 180 * M_PI ) * \cos ( $player->pitch / 180 * M_PI ) ),
							new Double ( "", -\sin ( $player->pitch / 180 * M_PI ) ),
							new Double ( "", \cos ( $player->yaw / 180 * M_PI ) * \cos ( $player->pitch / 180 * M_PI ) ) ] ),
					"Rotation" => new Enum ( "Rotation", [ 
							new Float ( "", $player->yaw ),
							new Float ( "", $player->pitch ) ] ) ] );
			
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
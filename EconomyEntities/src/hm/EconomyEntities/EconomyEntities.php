<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
* ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
*  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
*   ＼/_/  ＼/__/   ＼/_/ ＼/__/
* ( *you can redistribute it and/or modify *) */
namespace hm\EconomyEntities;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Short;
use pocketmine\item\Bow;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\entity\Arrow;
use pocketmine\level\format\FullChunk;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\Player;
use pocketmine\entity\Creature;
use pocketmine\entity\Ageable;
use pocketmine\entity\Skeleton;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\Server;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\command\PluginCommand;

class EconomyEntities extends PluginBase implements Listener {
	/*
	 * @var YML File variable
	 */
	public $configyml, $config, $rewardList;
	/*
	 * @var Bot Spawn locate list
	 */
	public $botspawnlist = [ ];
	/*
	 * @var Packet variable
	 */
	public $move_pk;
	public $damage_delay = [ ];
	public $dead_id = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->registerCommand ( $this->get ( "commands-entities" ), "EconomyEntities", "economyentities" );
		Entity::registerEntity ( Entities::class, true );
		$this->move_pk = new MovePlayerPacket ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->configyml = new Config ( $this->getDataFolder () . "config.yml", Config::YAML, array (
				"BotSpawn" => true,
				"RespawnTime" => 20,
				"BotSpawnCount" => 0,
				"Reward-DelayTime" => 60 
		) );
		$this->rewardList = (new Config ( $this->getDataFolder () . "rewardList.yml", Config::YAML, array (
				"chickin-item-id" => Item::EGG,
				"chickin-item-count" => 1,
				"villager-item-id" => [ 
						Item::WOOD,
						Item::WORKBENCH,
						Item::SNOW,
						Item::CLAY,
						Item::HAY_BALE,
						Item::SIGN,
						Item::MELON_SLICE,
						Item::COOKED_PORKCHOP,
						Item::WOODEN_AXE 
				],
				"villager-item-count" => 1,
				"skelleton-item-id" => Item::BONE,
				"skelleton-item-count" => 1,
				"pig-item-id" => Item::RAW_PORKCHOP,
				"pig-item-count" => 1,
				"cow-item-id" => Item::RAW_BEEF,
				"cow-item-count" => 1,
				"sheep-item-id" => Item::WOOL,
				"sheep-item-count" => 1 
		) ))->getAll ();
		$this->config = $this->configyml->getAll ();
		$this->initSpawn ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"BotUpdate" 
		] ), 10 );
	}
	public function onDisable() {
		$this->configyml->setAll ( $this->config );
		$this->configyml->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onDrop(PlayerItemConsumeEvent $event) {
		if ($event->getItem () instanceof Arrow) {
			if ($event->getItem ()->shootingEntity == null)
				return;
			if ($event->getItem ()->shootingEntity instanceof Entities)
				$event->setCancelled ();
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event->getEntity () instanceof Entities and $event instanceof EntityDamageByEntityEvent) {
			if (! $event->getDamager () instanceof Player)
				return;
			switch ($event->getEntity ()->getType ()) {
				case 10 :
					if (! isset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] )) {
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["chickin-item-id"], 0, $this->rewardList ["chickin-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-egg" ) );
						$this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					} else {
						$before = $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()];
						$after = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
						$timeout = intval ( $after - $before );
						if ($timeout < $this->config ["Reward-DelayTime"]) {
							$event->getDamager ()->sendMessage ( TextFormat::RED . $this->get ( "not-yet-ready-egg" ) );
						} else {
							if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
								return;
							$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
							$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["chickin-item-id"], 0, $this->rewardList ["chickin-item-count"] ) );
							$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-egg" ) );
							unset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] );
						}
					}
					$event->setCancelled ();
					break;
				case 11 :
					if (! isset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] )) {
						$event->setDamage ( 0 );
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["cow-item-id"], 0, $this->rewardList ["cow-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-steak" ) );
						$this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					} else {
						$before = $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()];
						$after = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
						$timeout = intval ( $after - $before );
						if ($timeout < $this->config ["Reward-DelayTime"]) {
							$event->getDamager ()->sendMessage ( TextFormat::RED . $this->get ( "not-yet-ready-steak" ) );
							$event->setCancelled ();
						} else {
							$event->setDamage ( 0 );
							if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
								return;
							$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
							$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["cow-item-id"], 0, $this->rewardList ["cow-item-count"] ) );
							$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-steak" ) );
							unset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] );
						}
					}
					break;
				case 12 :
					if (! isset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] )) {
						$event->setDamage ( 0 );
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["pig-item-id"], 0, $this->rewardList ["pig-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-pork" ) );
						$this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					} else {
						$before = $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()];
						$after = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
						$timeout = intval ( $after - $before );
						if ($timeout < $this->config ["Reward-DelayTime"]) {
							$event->getDamager ()->sendMessage ( TextFormat::RED . $this->get ( "not-yet-ready-pork" ) );
							$event->setCancelled ();
						} else {
							$event->setDamage ( 0 );
							if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
								return;
							$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
							$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["pig-item-id"], 0, $this->rewardList ["pig-item-count"] ) );
							$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-pork" ) );
							unset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] );
						}
					}
					break;
				case 13 :
					if (! isset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] )) {
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["sheep-item-id"], 0, $this->rewardList ["sheep-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-wool" ) );
						$this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					} else {
						$before = $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()];
						$after = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
						$timeout = intval ( $after - $before );
						if ($timeout < $this->config ["Reward-DelayTime"]) {
							$event->getDamager ()->sendMessage ( TextFormat::RED . $this->get ( "not-yet-ready-wool" ) );
						} else {
							if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
								return;
							$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
							$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["sheep-item-id"], 0, $this->rewardList ["sheep-item-count"] ) );
							$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-wool" ) );
							unset ( $this->damage_delay [$event->getDamager ()->getName ()] [$event->getEntity ()->getId ()] );
						}
					}
					$event->setCancelled ();
					break;
				case 15 :
					if ($event->getEntity ()->getHealth () - $event->getDamage () <= 0) {
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( array_rand ( $this->rewardList ["villager-item-id"] ), 0, $this->rewardList ["villager-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-villager-item" ) );
					}
					break;
				case 34 :
					if ($event->getEntity ()->getHealth () - $event->getDamage () <= 0) {
						if ($this->dead_id [$event->getDamager ()->getName ()] == $event->getEntity ()->getId ())
							return;
						$this->dead_id [$event->getDamager ()->getName ()] = $event->getEntity ()->getId ();
						$event->getDamager ()->getInventory ()->addItem ( Item::get ( $this->rewardList ["skelleton-item-id"], 0, $this->rewardList ["skelleton-item-count"] ) );
						$event->getDamager ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "success-get-skelleton-item" ) );
					}
			}
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->dead_id [$event->getPlayer ()->getName ()] = null;
	}
	public function onQuit(PlayerQuitEvent $event) {
		unset ( $this->dead_id [$event->getPlayer ()->getName ()] );
	}
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if (! isset ( $args [0] )) {
			$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-1" ) );
			$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-2" ) );
			$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-3" ) );
			$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-4" ) );
			$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-5" ) );
			return true;
		}
		if ($args [0] == $this->get ( "sub-commands-add" )) {
			if (! $sender instanceof Player) {
				if (! isset ( $args [1] )) {
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . " x:y:z" );
					return true;
				} else {
					$verify = explode ( ":", $args [1] );
					for($i = 0; $i <= 2; $i ++) {
						if (! isset ( $verify [$i] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . " x:y:z" );
							return true;
						}
						if (! is_numeric ( $verify [$i] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . " x:y:z" );
							return true;
						}
					}
					$botspawncount = $this->config ["BotSpawnCount"] ++;
					$this->config ["BotSpawnList"] [$botspawncount] ["pos"] = $args [1];
					$this->config ["BotSpawnList"] [$botspawncount] ["level"] = $sender->getLevel ()->getFolderName ();
					$sender->sendMessage ( TextFormat::RED . $this->get ( "info-prefix" ) . $botspawncount . $this->get ( "entity-locate-add-success" ) );
					$this->Respawn ( $botspawncount );
					return true;
				}
			}
			if (! isset ( $args [1] )) {
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "chicken" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "villager" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "pig" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "cow" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "sheep" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "skelleton" ) );
				return true;
			}
			$botspawncount = $this->config ["BotSpawnCount"] ++;
			switch (strtolower ( $args [1] )) {
				case $this->get ( "chicken" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 10;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "chicken" );
					break;
				case $this->get ( "cow" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 11;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "cow" );
					break;
				case $this->get ( "pig" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 12;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "pig" );
					break;
				case $this->get ( "sheep" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 13;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "sheep" );
					break;
				case $this->get ( "villager" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 15;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "villager" );
					break;
				case $this->get ( "skelleton" ) :
					$this->config ["BotSpawnList"] [$botspawncount] ["id"] = 34;
					$this->config ["BotSpawnList"] [$botspawncount] ['name'] = $this->get ( "skelleton" );
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "chicken" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "villager" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "pig" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "cow" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "sheep" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entity-add" ) . TextFormat::WHITE . $this->get ( "skelleton" ) );
					return true;
			}
			$this->config ["BotSpawnList"] [$botspawncount] ["pos"] = floor ( $sender->x ) . ":" . floor ( $sender->y ) . ":" . floor ( $sender->z );
			$this->config ["BotSpawnList"] [$botspawncount] ["level"] = $sender->getLevel ()->getFolderName ();
			$sender->sendMessage ( TextFormat::RED . $this->get ( "info-prefix" ) . $botspawncount . $this->get ( "entity-locate-add-success" ) );
			$this->Respawn ( $botspawncount );
			return true;
		}
		if ($args [0] == $this->get ( "sub-commands-clear" )) {
			$this->config ["BotSpawnCount"] = 0;
			unset ( $this->config ["BotSpawnList"] );
			$sender->sendMessage ( TextFormat::RED . $this->get ( "info-prefix" ) . " " . $this->get ( "entity-clear" ) );
			return true;
		}
		if ($args [0] == $this->get ( "sub-commands-enable" )) {
			$this->config ["BotSpawn"] = true;
			$this->getServer ()->broadcastMessage ( TextFormat::RED . $this->get ( "caution-prefix" ) . " " . $this->get ( "entity-spawn-enabled" ) );
			return true;
		}
		if ($args [0] == $this->get ( "sub-commands-disable" )) {
			$this->config ["BotSpawn"] = false;
			$this->getServer ()->broadcastMessage ( TextFormat::RED . $this->get ( "info-prefix" ) . " " . $this->get ( "entity-spawn-disabled" ) );
			return true;
		}
		$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-1" ) );
		$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-2" ) );
		$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-3" ) );
		$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-4" ) );
		$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "entities-help-5" ) );
		return true;
	}
	/*
	 * @var SpawnSchedule
	 */
	public function initSpawn() {
		if ($this->config ["BotSpawn"] == false)
			return;
		$botspawncount = $this->config ["BotSpawnCount"];
		for($i = 1; $i <= $botspawncount; $i ++) {
			if (! isset ( $this->config ["BotSpawnList"] [$i] ))
				return;
			if (! isset ( $this->botspawnlist [$i] )) {
				$this->botspawnlist [$i] ["isSpawn"] = 1;
				$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
				$this->botspawnlist [$i] ["Ent"] = $this->BotSpawn ( $pos [0], $pos [1], $pos [2], $i );
			} else {
				if ($this->botspawnlist [$i] ["isSpawn"] == 0) {
					$this->botspawnlist [$i] ["isSpawn"] = 1;
					$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
					$this->botspawnlist [$i] ["Ent"] = $this->BotSpawn ( $pos [0], $pos [1], $pos [2], $i );
				}
			}
		}
	}
	/*
	 * @var YML Spawning Bot function
	 */
	public function BotSpawn($x, $y, $z, $i) {
		$level = $this->getServer ()->getLevelByName ( $this->config ["BotSpawnList"] [$i] ["level"] );
		if ($level == null)
			return;
		if ($level->isChunkGenerated ( $x, $z ))
			$level->generateChunk ( $x, $z );
		$chunk = $level->getChunk ( $x >> 4, $z >> 4 );
		if (! ($chunk instanceof FullChunk))
			return false;
		$nbt = new Compound ( "", [ 
				"Pos" => new Enum ( "Pos", [ 
						new Double ( "", $x ),
						new Double ( "", $y ),
						new Double ( "", $z ) 
				] ),
				"Motion" => new Enum ( "Motion", [ 
						new Double ( "", 0 ),
						new Double ( "", 0 ),
						new Double ( "", 0 ) 
				] ),
				"Rotation" => new Enum ( "Rotation", [ 
						new Float ( "", 0 ),
						new Float ( "", 0 ) 
				] ) 
		] );
		$nbt->Health = new Short ( "Health", 15 );
		$id = $this->config ["BotSpawnList"] [$i] ["id"];
		$name = $this->config ["BotSpawnList"] [$i] ['name'];
		$entity = new Entities ( $chunk, $nbt );
		
		if ($entity instanceof Entity) {
			$entity->ID = $id;
			$entity->name = $name;
			$entity->spawnToAll ();
			return $entity;
		}
		return false;
	}
	/*
	 * @var Bot Movement
	 */
	public function Respawn($i) {
		$this->botspawnlist [$i] ["isSpawn"] = 1;
		$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
		$this->botspawnlist [$i] ["Ent"] = $this->BotSpawn ( $pos [0], $pos [1], $pos [2], $i );
	}
	public function BotUpdate() {
		for($i = 1; $i <= $this->config ["BotSpawnCount"]; $i ++) {
			if (! isset ( $this->botspawnlist [$i] ["Ent"] ))
				continue;
			if (! $this->botspawnlist [$i] ["Ent"] instanceof Entities)
				return;
			if (! $this->botspawnlist [$i] ["isSpawn"])
				continue;
			if ($this->botspawnlist [$i] ["Ent"]->dead) {
				$this->botspawnlist [$i] ["Ent"]->close ();
				$this->botspawnlist [$i] ["isSpawn"] = 0;
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"Respawn" 
				], [ 
						$i 
				] ), 20 * $this->config ['RespawnTime'] );
				continue;
			}
			$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
			foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
				$mx = abs ( $pos [0] - $player->x );
				$my = abs ( $pos [1] - $player->y );
				$mz = abs ( $pos [2] - $player->z );
				if (! ($mx <= 14 and $my <= 8 and $mz <= 14))
					continue;
				
				$x = $player->x - $pos [0];
				$y = $player->y - $pos [1];
				$z = $player->z - $pos [2];
				$dXZ = sqrt ( pow ( $x, 2 ) + pow ( $z, 2 ) );
				$atn = atan2 ( $z, $x );
				$getyaw = rad2deg ( $atn - M_PI_2 );
				$getpitch = rad2deg ( - atan2 ( $y, $dXZ ) );
				
				$this->move_pk->eid = $this->botspawnlist [$i] ["Ent"]->getID ();
				$this->move_pk->x = $pos [0];
				$this->move_pk->y = $pos [1];
				$this->move_pk->z = $pos [2];
				$this->move_pk->yaw = $getyaw;
				$this->move_pk->pitch = $getpitch;
				$this->move_pk->bodyYaw = $getyaw;
				
				foreach ( $this->getServer ()->getOnlinePlayers () as $player )
					$player->directDataPacket ( $this->move_pk );
				
				break;
			}
			$id = $this->config ["BotSpawnList"] [$i] ["id"];
			if ($id == 34) {
				if ($this->botspawnlist [$i] ["Ent"]->hit instanceof Player) {
					if ($this->botspawnlist [$i] ["Ent"]->hitcool >= 2) {
						$this->botspawnlist [$i] ["Ent"]->hitcool = 0;
					} else {
						$mx = abs ( $pos [0] - $this->botspawnlist [$i] ["Ent"]->hit->x );
						$my = abs ( $pos [1] - $this->botspawnlist [$i] ["Ent"]->hit->y );
						$mz = abs ( $pos [2] - $this->botspawnlist [$i] ["Ent"]->hit->z );
						
						if (($mx <= 35 and $my <= 15 and $mz <= 35)) {
							$this->Skelleton_attack ( $i, $this->botspawnlist [$i] ["Ent"]->hit, 1.3 );
							$this->botspawnlist [$i] ["Ent"]->hit = null;
						}
						$this->botspawnlist [$i] ["Ent"]->hitcool ++;
					}
					continue;
				}
				foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
					$mx = abs ( $pos [0] - $player->x );
					$my = abs ( $pos [1] - $player->y );
					$mz = abs ( $pos [2] - $player->z );
					if (($mx <= 12 and $my <= 8 and $mz <= 12) and $player->spawned and ! $player->dead and $player->isSurvival ())
						$this->Skelleton_attack ( $i, $player, 1.3 );
				}
			}
			if ($id == 15) {
				if ($this->botspawnlist [$i] ["Ent"]->hit instanceof Player) {
					if ($this->botspawnlist [$i] ["Ent"]->hitcool >= 2) {
						$this->botspawnlist [$i] ["Ent"]->hitcool = 0;
					} else {
						$mx = abs ( $pos [0] - $this->botspawnlist [$i] ["Ent"]->hit->x );
						$my = abs ( $pos [1] - $this->botspawnlist [$i] ["Ent"]->hit->y );
						$mz = abs ( $pos [2] - $this->botspawnlist [$i] ["Ent"]->hit->z );
						
						if (($mx <= 35 and $my <= 15 and $mz <= 35)) {
							$this->Villager_attack ( $i, $this->botspawnlist [$i] ["Ent"]->hit, 1.3 );
							$this->botspawnlist [$i] ["Ent"]->hit = null;
						}
						$this->botspawnlist [$i] ["Ent"]->hitcool ++;
					}
					continue;
				}
			}
		}
	}
	public function Villager_attack($i, $player, $f) {
		$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
		
		$x = $player->x - $pos [0];
		$y = $player->y - $pos [1];
		$z = $player->z - $pos [2];
		
		$dXZ = sqrt ( pow ( $x, 2 ) + pow ( $z, 2 ) );
		$atn = atan2 ( $z, $x );
		$getyaw = rad2deg ( $atn - M_PI_2 );
		$getpitch = rad2deg ( - atan2 ( $y, $dXZ ) );
		$cos = cos ( $getpitch / 180 * M_PI );
		
		$this->move_pk->eid = $this->botspawnlist [$i] ["Ent"]->getID ();
		
		$this->move_pk->x = $pos [0];
		$this->move_pk->y = $pos [1];
		$this->move_pk->z = $pos [2];
		$this->move_pk->yaw = $getyaw;
		$this->move_pk->pitch = $getpitch;
		$this->move_pk->bodyYaw = $getyaw;
		
		foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			$player->directDataPacket ( $this->move_pk );
		
		if (! isset ( $this->botspawnlist [$i] ["attackcool"] )) {
			$this->botspawnlist [$i] ["attackcool"] = 1;
			return false;
		}
		if ($this->botspawnlist [$i] ["attackcool"] >= 6) {
			$nbt = new Compound ( "", [ 
					"Pos" => new Enum ( "Pos", [ 
							new Double ( "", $pos [0] - sin ( $getyaw / 180 * M_PI ) * $cos ),
							new Double ( "", $pos [1] + 1.6 ),
							new Double ( "", $pos [2] + cos ( $getyaw / 180 * M_PI ) * $cos * $f ) 
					] ),
					"Motion" => new Enum ( "Motion", [ 
							new Double ( "", - sin ( $getyaw / 180 * M_PI ) * $cos * $f ),
							new Double ( "", - sin ( $getpitch / 180 * M_PI ) * $f ),
							new Double ( "", cos ( $getyaw / 180 * M_PI ) * $cos * $f ) 
					] ),
					"Rotation" => new Enum ( "Rotation", [ 
							new Float ( "", $getyaw ),
							new Float ( "", $getpitch ) 
					] ) 
			] );
			
			$this->botspawnlist [$i] ["attackcool"] = 0;
			$chunk = $this->botspawnlist [$i] ["Ent"]->getLevel ()->getChunk ( $pos [0] >> 4, $pos [2] >> 4 );
			$snowball = Entity::createEntity ( "Snowball", $chunk, $nbt, $this->botspawnlist [$i] ["Ent"] );
			$snowball->setMotion ( $snowball->getMotion ()->multiply ( $f ) );
			$snowball->spawnToAll ();
		} else {
			$this->botspawnlist [$i] ["attackcool"] ++;
		}
		return true;
	}
	public function Skelleton_attack($i, $player, $f) {
		$pos = explode ( ":", $this->config ["BotSpawnList"] [$i] ["pos"] );
		
		$x = $player->x - $pos [0];
		$y = $player->y - $pos [1];
		$z = $player->z - $pos [2];
		
		$dXZ = sqrt ( pow ( $x, 2 ) + pow ( $z, 2 ) );
		$atn = atan2 ( $z, $x );
		$getyaw = rad2deg ( $atn - M_PI_2 );
		$getpitch = rad2deg ( - atan2 ( $y, $dXZ ) );
		$cos = cos ( $getpitch / 180 * M_PI );
		
		$this->move_pk->eid = $this->botspawnlist [$i] ["Ent"]->getID ();
		
		$this->move_pk->x = $pos [0];
		$this->move_pk->y = $pos [1];
		$this->move_pk->z = $pos [2];
		$this->move_pk->yaw = $getyaw;
		$this->move_pk->pitch = $getpitch;
		$this->move_pk->bodyYaw = $getyaw;
		
		foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			$player->directDataPacket ( $this->move_pk );
		
		if (! isset ( $this->botspawnlist [$i] ["attackcool"] )) {
			$this->botspawnlist [$i] ["attackcool"] = 1;
			return false;
		}
		if ($this->botspawnlist [$i] ["attackcool"] >= 6) {
			$nbt = new Compound ( "", [ 
					"Pos" => new Enum ( "Pos", [ 
							new Double ( "", $pos [0] - sin ( $getyaw / 180 * M_PI ) * $cos ),
							new Double ( "", $pos [1] + 1.6 ),
							new Double ( "", $pos [2] + cos ( $getyaw / 180 * M_PI ) * $cos * $f ) 
					] ),
					"Motion" => new Enum ( "Motion", [ 
							new Double ( "", - sin ( $getyaw / 180 * M_PI ) * $cos * $f ),
							new Double ( "", - sin ( $getpitch / 180 * M_PI ) * $f ),
							new Double ( "", cos ( $getyaw / 180 * M_PI ) * $cos * $f ) 
					] ),
					"Rotation" => new Enum ( "Rotation", [ 
							new Float ( "", $getyaw ),
							new Float ( "", $getpitch ) 
					] ) 
			] );
			
			$this->botspawnlist [$i] ["attackcool"] = 0;
			$chunk = $this->botspawnlist [$i] ["Ent"]->getLevel ()->getChunk ( $pos [0] >> 4, $pos [2] >> 4 );
			$arrow = new Arrow ( $chunk, $nbt, $this->botspawnlist [$i] ["Ent"] );
			$ev = new EntityShootBowEvent ( $this->botspawnlist [$i] ["Ent"], new Bow (), $arrow, $f );
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"removeArrow" 
			], [ 
					$arrow 
			] ), 40 );
			$this->getServer ()->getPluginManager ()->callEvent ( $ev );
			if ($ev->isCancelled ()) {
				$arrow->kill ();
				$arrow->close ();
			} else {
				$arrow->spawnToAll ();
			}
		} else {
			$this->botspawnlist [$i] ["attackcool"] ++;
		}
		return true;
	}
	/*
	 * @var removeArrow function
	 */
	public function removeArrow(Arrow $arrow) {
		$arrow->kill ();
		$arrow->close ();
	}
}
class Entities extends Creature {
	public $ID = null;
	public $addmob_pk = null;
	public $removeentity_pk = null;
	public $setmotion_pk = null;
	public $entityevent_pk = null;
	public $width = 0.6;
	public $length = 0.6;
	public $height = 1.8;
	public $hit = null;
	public $hitcool = 0;
	public $type = null;
	public $name = null;
	public function getType() {
		return $this->ID;
	}
	public function getName() {
		return $this->name;
	}
	protected function initEntity() {
		parent::initEntity ();
		if ($this->addmob_pk == null)
			$this->addmob_pk = new AddMobPacket ();
		if ($this->setmotion_pk == null)
			$this->setmotion_pk = new SetEntityMotionPacket ();
		if ($this->entityevent_pk == null)
			$this->entityevent_pk = new EntityEventPacket ();
		if ($this->removeentity_pk == null)
			$this->removeentity_pk = new RemoveEntityPacket ();
		$this->namedtag->id = new String ( "id", "BOT" );
	}
	public function spawnTo(Player $player) {
		parent::spawnTo ( $player );
		
		$this->addmob_pk->eid = $this->getID ();
		$this->addmob_pk->type = $this->ID;
		$this->addmob_pk->x = $this->x;
		$this->addmob_pk->y = $this->y;
		$this->addmob_pk->z = $this->z;
		$this->addmob_pk->yaw = $this->yaw;
		$this->addmob_pk->pitch = $this->pitch;
		$this->addmob_pk->metadata = $this->getData ();
		$player->dataPacket ( $this->addmob_pk );
		
		$this->setmotion_pk->entities = [ 
				$this->getID (),
				$this->motionX,
				$this->motionY,
				$this->motionZ 
		];
		$player->dataPacket ( $this->setmotion_pk );
	}
	public function despawnFrom(Player $player) {
		if (isset ( $this->hasSpawned [$player->getID ()] )) {
			$this->entityevent_pk->eid = $this->id;
			$this->entityevent_pk->event = 3;
			if ($player != null) {
				$player->dataPacket ( $this->entityevent_pk );
				
				$this->removeentity_pk->eid = $this->id;
				$this->server->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$player,
						"dataPacket" 
				], [ 
						$this->removeentity_pk 
				] ), 23 );
			}
			unset ( $this->hasSpawned [$player->getID ()] );
		}
	}
	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC) {
		parent::attack ( $damage, $source );
		if (! $this->hit instanceof Player and $source instanceof EntityDamageByEntityEvent)
			if ($source->getDamager () instanceof Player)
				$this->hit = $source->getDamager ();
	}
	public function getData() {
		$flags = 0;
		$flags |= $this->fireTicks > 0 ? 1 : 0;
		return [ 
				0 => [ 
						"type" => 0,
						"value" => $flags 
				],
				1 => [ 
						"type" => 1,
						"value" => $this->airTicks 
				],
				16 => [ 
						"type" => 0,
						"value" => 0 
				],
				17 => [ 
						"type" => 6,
						"value" => [ 
								0,
								0,
								0 
						] 
				] 
		];
	}
}
?>
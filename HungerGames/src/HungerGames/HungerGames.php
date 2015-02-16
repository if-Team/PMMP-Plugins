<?php

namespace HungerGames;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\scheduler\PluginTask;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\block\BlockBreakEvent;

class HungerGames extends PluginBase implements Listener {
	public $config, $config_data;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->users = [ ];
		$this->deathcool = [ ];
		$this->attackcool = [ ];
		@mkdir ( $this->getDataFolder () );
		$this->config = new Config ( $this->getDataFolder () . "hunger_data.yml", Config::YAML );
		$this->config_data = $this->config->getAll ();
		$this->getLogger ()->info ( "HungerGames Loaded" );
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_data );
		$this->config->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if ($command->getName () == "hunger") {
			if (! ($sender instanceof Player)) {
				$sender->sendMessage ( TextFormat::RED . "명령어는 인게임에서만 사용가능합니다" );
			}
			if (! isset ( $args [0] )) return false;
			switch ($args [0]) {
				case 'spawn' :
					if (isset ( $args [1] )) {
						if ($args [1] == "add") {
							if (isset ( $this->config_data ["spawn"] ["count"] )) {
								$count = ++ $this->config_data ["spawn"] ["count"];
								$this->config_data ["spawn"] [$count] = floor ( $sender->x ) . ":" . floor ( $sender->y ) . ":" . floor ( $sender->z );
							} else {
								$count = $this->config_data ["spawn"] ["count"] = 1;
								$this->config_data ["spawn"] [1] = floor ( $sender->x ) . ":" . floor ( $sender->y ) . ":" . floor ( $sender->z );
							}
							$sender->sendMessage ( TextFormat::DARK_AQUA . $count . "번째 스폰구역으로 추가되었습니다." );
							break;
						} else if ($args [1] == "clear") {
							unset ( $this->config_data ["spawn"] );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "스폰구역 세팅이 초기화되었습니다" );
							break;
						}
					}
					$sender->sendMessage ( TextFormat::DARK_AQUA . "사용법 : /hunger spawn <add | clear>" );
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . "아래 명령어를 사용가능합니다" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/hunger spawn - 랜덤스폰장소 설정" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "*-----------------------------" );
					break;
			}
			return true;
		}
	}
	public function Respawn(PlayerRespawnEvent $event) {
		$entity = $event->getPlayer ();
		if (isset ( $this->config_data ["spawn"] ["count"] )) {
			$rand = rand ( 1, $this->config_data ["spawn"] ["count"] );
			$position = $this->config_data ["spawn"] [$rand];
			
			$e = explode ( ":", $position );
			$pos = new Position ( $e [0], $e [1], $e [2], $entity->getLevel () );
			$event->setRespawnPosition ( $pos );
		}
		$entity->sendMessage ( TextFormat::RED . "[주의] 리스폰 보호는 15초간 지속됩니다 !" );
		if (isset ( $this->users [$entity->getName ()] )) {
			$this->users [$entity->getName ()]->ATField = 1;
			$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
					$this,
					"UnATField" ], [ 
					$event->getPlayer () ] ), 20 * 15 );
		}
	}
	public function BlockBreak(BlockBreakEvent $event) {
		if ($event->getBlock ()->getId () == Item::NETHER_REACTOR) if (! $event->getPlayer ()->isOp ()) $event->setCancelled ();
	}
	public function onAttack(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			$victim = $event->getEntity ();
			$murder = $event->getDamager ();
			if ($victim instanceof Player) {
				if ($this->users [$victim->getName ()]->ATField == 1) return;
				if (isset ( $this->users [$victim->getName ()]->firstpos )) {
					$fpos = $this->users [$victim->getName ()]->firstpos;
					if ($fpos->x == $victim->x and $fpos->y == $victim->y and $fpos->z == $victim->z) return;
				}
			}
			if ($murder instanceof Player) {
				if (isset ( $this->deathcool [$murder->getName ()] )) {
					if (time () - $this->deathcool [$murder->getName ()] <= 0.1) return;
					$item = $murder->getInventory ()->getItemInHand ()->getID ();
					$totalDamaged = 0;
					if ($item == Item::IRON_SWORD or $item == Item::WOODEN_SWORD or $item == Item::STONE_SWORD or $item == Item::DIAMOND_SWORD or $item == Item::GOLD_SWORD) {
						$totalDamaged = 5;
					} else if ($item == Item::WOODEN_AXE or $item == Item::STONE_AXE or $item == Item::DIAMOND_AXE or $item == Item::GOLD_AXE) {
						$totalDamaged = 4;
					} else {
						$totalDamaged = 2;
					}
					if ($victim instanceof Player) {
						$totalDamaged -= $this->ArmorDamageCalc ( $victim );
					}
					if ($totalDamaged > 0) $event->setDamage ( $event->getDamage () + $totalDamaged );
					if (isset ( $this->attackcool [$murder->getName ()] )) if (time () - $this->attackcool [$murder->getName ()] <= 1) return;
					if ($victim->getHealth () - $event->getDamage () <= 0) {
						$this->KillUpdate ( $murder, $victim );
						$this->attackcool [$murder->getName ()] = time ();
					}
				}
				$this->deathcool [$murder->getName ()] = time ();
			}
		}
	}
	public function checkArrow(ProjectileLaunchEvent $event) {
		if ($entity instanceof Arrow) $this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$this,
				"removeArrow" ], [ 
				$event ] ), 20 );
	}
	public function checkLogin(PlayerLoginEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->users [$player->getName ()] )) {
			$this->users [$player->getName ()] = new HungerGameSession ();
			$this->users [$player->getName ()]->firstpos = new Vector3 ( $player->x, $player->y, $player->z );
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->config_data [$player->getName ()] )) {
			$this->config_data [$player->getName ()] ["kill"] = 0;
			$this->config_data [$player->getName ()] ["death"] = 0;
		}
	}
	public function onDeath(PlayerDeathEvent $event) {
		$event->setDrops ( [ ] );
		$entity = $event->getEntity ();
		if (isset ( $this->users [$entity->getName ()] )) {
			$this->users [$entity->getName ()]->sakura = [ ];
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		$item = $event->getItem ()->getID ();
		
		if (($block->getID () == Item::NETHER_REACTOR or $block->getID () == Item::DIAMOND_BLOCK) and isset ( $this->users [$player->getName ()] )) {
			$this->users [$player->getName ()]->TouchCheck ( $player, $block->getX (), $block->getY (), $block->getZ (), 1 );
			$event->setCancelled ();
			return;
		}
		if ($item == Item::IRON_SWORD or $item == Item::WOODEN_SWORD or $item == Item::STONE_SWORD or $item == Item::DIAMOND_SWORD or $item == Item::GOLD_SWORD or $item == Item::WOODEN_AXE or $item == Item::STONE_AXE or $item == Item::DIAMOND_AXE or $item == Item::GOLD_AXE) {
			$block = $event->getBlock ()->getSide ( 1 );
			if ($block->getID () == Item::AIR) {
				$block->getLevel ()->setBlock ( $block, Block::get ( Item::FIRE, 0 ) );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"removeBlock" ], [ 
						$block ] ), 100 );
			}
		}
	}
	public function UnATField(Player $player) {
		$this->users [$player->getName ()]->ATField = 0;
		$player->sendMessage ( TextFormat::RED . "[주의] 리스폰 보호가 해제되었습니다 !" );
	}
	public function KillUpdate($murder, $victim) {
		if ($victim instanceof Player and $murder instanceof Player) {
			$this->config_data [$murder->getName ()] ["kill"] ++;
			$this->config_data [$victim->getName ()] ["death"] ++;
			$mi = "(K" . $this->config_data [$murder->getName ()] ["kill"] . "/D" . $this->config_data [$murder->getName ()] ["death"] . ")";
			$vi = "(K" . $this->config_data [$victim->getName ()] ["kill"] . "/D" . $this->config_data [$victim->getName ()] ["death"] . ")";
			$this->getServer ()->broadcastMessage ( TextFormat::RED . $murder->getName () . $mi . "´님이" . $victim->getName () . $vi . "님을 살해 !" );
		}
	}
	public function ArmorDamageCalc(Player $player) {
		if ($player instanceof Player and $player->spawned === true) {
			if ($player->getInventory () == NULL) return 0;
			if ($player->getInventory ()->getArmorContents () == NULL) return 0;
			foreach ( $player->getInventory ()->getArmorContents () as $ar ) {
				$arlist = array (
						Item::LEATHER_CAP,
						Item::CHAIN_HELMET,
						Item::IRON_HELMET,
						Item::DIAMOND_HELMET,
						Item::GOLD_HELMET );
				foreach ( $arlist as $arl ) {
					if ($ar == new Item ( $arl, 0, 1 )) {
						if ($arl == Item::LEATHER_CAP) $nocount = 2;
						if ($arl == Item::CHAIN_HELMET) $nocount = 2;
						if ($arl == Item::IRON_HELMET) $nocount = 2;
						if ($arl == Item::DIAMOND_HELMET) $nocount = 3;
						if ($arl == Item::GOLD_HELMET) $nocount = 3;
					}
				}
			}
			if (isset ( $nocunt )) {
				return $nocunt;
			} else {
				return 0;
			}
		}
	}
	public function ShockWave($x, $y, $z, $radius, $damage, $murder) {
		$dcount = $damage;
		$exp = new ExplodePacket ();
		$exp->x = $x;
		$exp->y = $y;
		$exp->z = $z;
		$exp->radius = 32;
		$entities = $this->getServer ()->getDefaultLevel ()->getEntities ();
		foreach ( $entities as $victim ) {
			if ($victim instanceof Player) {
				$cx = abs ( $x - $victim->x );
				$cy = abs ( $y - $victim->y );
				$cz = abs ( $z - $victim->z );
				if ($victim instanceof Player) {
					$dcount -= $this->ArmorDamageCalc ( $victim );
					if (isset ( $this->users [$victim->getName ()]->firstpos )) {
						$fpos = $this->users [$victim->getName ()]->firstpos;
						if ($fpos->x == $victim->x and $fpos->y == $victim->y and $fpos->z == $victim->z) return;
					}
				}
				if (isset ( $this->users [$victim->getName ()] )) {
					if ($this->users [$victim->getName ()]->ATField == 0) {
						if ($cx <= 20 and $cz <= 20) {
							$victim->directDataPacket ( $exp );
						}
						if ($cx <= $radius and $cy <= $radius and $cz <= $radius) {
							if ($victim->getHealth () - $dcount <= 0 and $victim->spawned == true and $victim->dead == false) {
								$this->KillUpdate ( $murder, $victim );
							}
							$victim->attack ( $dcount, EntityDamageEvent::CAUSE_ENTITY_ATTACK );
						}
					}
				}
			}
		}
	}
	public function removeArrow($event) {
		$arrow = $event->getProjectile ();
		$murder = $event->getEntity ();
		$this->ShockWave ( $arrow->x, $arrow->y, $arrow->z, 5, 5, $murder );
		$reflection_class = new \ReflectionClass ( $arrow );
		$property = $reflection_class->getProperty ( 'age' );
		$property->setAccessible ( true );
		$property->setValue ( $arrow, 7000 );
	}
	public function removeBlock($block) {
		$block->getLevel ()->setBlock ( $block, Block::get ( 0, 0 ) );
	}
}
?>
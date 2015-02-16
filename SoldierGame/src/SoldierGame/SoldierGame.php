<?php

namespace SoldierGame;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\entity\Snowball;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\entity\Creature;

class SoldierGame extends PluginBase implements Listener {
	public $config, $config_Data;
	public function onEnable() {
		$this->config = new Config ( $this->getDataFolder () . "gameData.yml", Config::YAML, [ 
				"enable-soldiergame" => 1,
				"enable-explode" => 1,
				"enable-broadcast" => 1 ] );
		$this->config_Data = $this->config->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () ) == "soldiergame") {
			if (! $sender->hasPermission ( "soldiergame" )) return false;
			if (! isset ( $args [0] )) {
				$this->onHelp ( $sender );
				return true;
			}
			switch (strtolower ( $args [0] )) {
				case "enable" :
					$this->config_Data ["enable-soldiergame"] = 1;
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 눈싸움이 활성화되었습니다." );
					break;
				case "disable" :
					$this->config_Data ["enable-soldiergame"] = 0;
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 눈싸움이 비활성화되었습니다." );
					break;
				case "explode" :
					if ($this->config_Data ["enable-explode"]) {
						$this->config_Data ["enable-explode"] = 0;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 폭발이 비활성화되었습니다." );
					} else {
						$this->config_Data ["enable-explode"] = 1;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 폭발이 활성화되었습니다." );
					}
					break;
				case "broadcast" :
					if ($this->config_Data ["enable-broadcast"]) {
						$this->config_Data ["enable-broadcast"] = 0;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 전투알림이 비활성화되었습니다." );
					} else {
						$this->config_Data ["enable-broadcast"] = 1;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] 전투알림이 활성화되었습니다." );
					}
					break;
				case "score" :
					if (isset ( $args [1] )) {
						if (isset ( $this->config_Data [$args [1]] )) {
							$score = "(K" . $this->config_Data [$args [1]] ["kill"] . "/D" . $this->config_Data [$args [1]] ["death"] . ")";
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] $args[1] - " . $score );
						} else {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] $args[1] - 전적을 찾을 수 없습니다." );
						}
					} else {
						if (isset ( $this->config_Data [$sender->getName ()] )) {
							$score = "(K" . $this->config_Data [$sender->getName ()] ["kill"] . "/D" . $this->config_Data [$sender->getName ()] ["death"] . ")";
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] " . $sender->getName () . " - " . $score );
						} else {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[SoldierGame] " . $sender->getName () . " - 전적을 찾을 수 없습니다." );
						}
					}
					break;
				default :
					$this->onHelp ( $sender );
					break;
			}
			return true;
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (! isset ( $this->config_Data [$event->getPlayer ()->getName ()] )) {
			$this->config_Data [$event->getPlayer ()->getName ()] ["kill"] = 0;
			$this->config_Data [$event->getPlayer ()->getName ()] ["death"] = 0;
		}
	}
	public function onHelp(Player $sender) {
		if ($sender->isOp ()) {
			$sender->sendMessage ( TextFormat::DARK_AQUA . "/SoldierGame enable - 눈싸움 활성화" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "/SoldierGame disable - 눈싸움 비활성화" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "/SoldierGame explode - 폭발 활성|비활성화" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "/SoldierGame broadcast - 전투알림 활성|비활성화" );
		}
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/SoldierGame score <유저명> - 전적표시." );
	}
	public function checkEnableSoldierGame() {
		return ( bool ) $this->config_Data ["enable-soldiergame"];
	}
	public function checkEnableExplode() {
		return ( bool ) $this->config_Data ["enable-explode"];
	}
	public function checkEnableBroadcast() {
		return ( bool ) $this->config_Data ["enable-broadcast"];
	}
	public function SnowballExplode(EntityDespawnEvent $event) {
		if ($event->getType () == 81 and $this->checkEnableExplode ()) $this->SoldierGame ( $event->getEntity () );
	}
	public function SoldierGame(Entity $entity) {
		if ($this->checkEnableSoldierGame () and $entity->shootingEntity instanceof Player) $this->shockWave ( $entity->x, $entity->y, $entity->z, 5, 5, $entity->shootingEntity );
	}
	public function blockBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		if ($block->getId () == Block::SNOW_LAYER or $block->getId () == Block::SNOW_BLOCK) $player->getInventory ()->addItem ( Item::get ( Item::SNOWBALL, 0, 4 ) );
	}
	public function shockWave($x, $y, $z, $radius, $damage, Player $murder) {
		$exp = new ExplodePacket ();
		$exp->x = $x;
		$exp->y = $y;
		$exp->z = $z;
		$exp->radius = 32;
		foreach ( $murder->getLevel ()->getEntities () as $victim ) {
			if (! $victim instanceof Creature) return;
			$cx = abs ( $x - $victim->x );
			$cz = abs ( $z - $victim->z );
			if ($victim instanceof Player and $cx <= 20 and $cz <= 20) {
				$victim->directDataPacket ( $exp );
			}
			if ($cx <= $radius and $cz <= $radius) {
				$victim->attack ( $damage, EntityDamageEvent::CAUSE_ENTITY_ATTACK );
				if ($victim->getHealth () <= 0) {
					if ($this->checkEnableBroadcast ()) $this->killUpdate ( $murder, $victim );
				}
			}
		}
	}
	public function killUpdate($murder, $victim) {
		if ($victim->getName () == null) return;
		if ($victim == $murder) return;
		$this->config_Data [$murder->getName ()] ["kill"] ++;
		if ($victim instanceof Player) $this->config_Data [$victim->getName ()] ["death"] ++;
		$mi = "(K" . $this->config_Data [$murder->getName ()] ["kill"] . "/D" . $this->config_Data [$murder->getName ()] ["death"] . ")";
		if ($victim instanceof Player) {
			$vi = "(K" . $this->config_Data [$victim->getName ()] ["kill"] . "/D" . $this->config_Data [$victim->getName ()] ["death"] . ")";
		} else {
			$vi = "";
		}
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if ($player == $murder) {
				$player->sendMessage ( TextFormat::RED . $victim->getName () . $vi . "님을 살해 하셨습니다 ! " );
				return;
			}
			$player->sendMessage ( TextFormat::RED . $murder->getName () . $mi . "´님이" . $victim->getName () . $vi . "님을 살해 !" );
		}
	}
}

?>
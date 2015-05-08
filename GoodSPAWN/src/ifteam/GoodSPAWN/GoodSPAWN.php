<?php

namespace ifteam\GoodSPAWN;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\math\Vector3;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;

class GoodSPAWN extends PluginBase implements Listener {
	public $config, $config_Data;
	public $m_version = 3;
	public $spawn_queue = [ ];
	public $death_queue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ "spawns" => [ ] ] );
		$this->config_Data = $this->config->getAll ();
		
		$this->registerCommand ( $this->get ( "commands-spawn" ), "goodspawn.spawn", $this->get ( "spawn-desc" ) . "/" . $this->get ( "commands-spawn" ) );
		$this->registerCommand ( $this->get ( "commands-setspawn" ), "goodspawn.setspawn", $this->get ( "setspawn-desc" ) . "/" . $this->get ( "commands-setspawn" ) );
		$this->registerCommand ( $this->get ( "commands-spawnclear" ), "goodspawn.spawnclear", $this->get ( "spawnclear-desc" ) . "/" . $this->get ( "commands-spawnclear" ) );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function messagesUpdate() {
		if (! isset ( $this->messages ["default-language"] ["m_version"] )) {
			$this->saveResource ( "messages.yml", true );
			$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		} else {
			if ($this->messages ["default-language"] ["m_version"] < $this->m_version) {
				$this->saveResource ( "messages.yml", true );
				$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
			}
		}
	}
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	public function onLogin(PlayerLoginEvent $event) {
		if (isset ( $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] )) {
			$pos = new Vector3 ( $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["x"], $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["y"], $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["z"] );
			$event->getPlayer ()->teleport ( $pos, $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["yaw"], $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["pitch"] );
			unset ( $this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] );
			return;
		}
		if (! isset ( $this->spawn_queue [$event->getPlayer ()->getName ()] )) {
			$this->spawn_queue [$event->getPlayer ()->getName ()] = 1;
			$pos = $this->getSpawn ( $event->getPlayer () );
			if ($pos != null) $event->getPlayer ()->teleport ( $pos [0], $pos [1], $pos [2] );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["x"] = $event->getPlayer ()->x;
		$this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["y"] = $event->getPlayer ()->y;
		$this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["z"] = $event->getPlayer ()->z;
		$this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["yaw"] = $event->getPlayer ()->yaw;
		$this->config_Data ["backPos"] [$event->getPlayer ()->getName ()] ["pitch"] = $event->getPlayer ()->pitch;
	}
	public function onRespawn(PlayerRespawnEvent $event) {
		if (isset ( $this->death_queue [$event->getPlayer ()->getName ()] )) {
			$pos = $this->getSpawn ( $event->getPlayer () );
			if ($pos != null) {
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ $this,"delayRespawn" ], [ $event->getPlayer (),$pos [0] ] ), 20 );
			}
			unset ( $this->death_queue [$event->getPlayer ()->getName ()] );
		}
	}
	public function delayRespawn(Player $player, Position $position) {
		$player->teleport ( $position );
	}
	public function onDeath(PlayerDeathEvent $event) {
		if (! isset ( $this->death_queue [$event->getEntity ()->getName ()] )) $this->death_queue [$event->getEntity ()->getName ()] = 1;
	}
	public function getSpawn(Player $player) {
		if (! isset ( $this->config_Data ["spawns"] ) or count ( $this->config_Data ["spawns"] ) == 0) return null;
		$rand = mt_rand ( 0, count ( $this->config_Data ["spawns"] ) - 1 );
		$epos = explode ( ":", $this->config_Data ["spawns"] [$rand] );
		$level = $this->getServer ()->getLevelByName ( $epos [5] );
		if (! $level instanceof Level) return null;
		return [ new Position ( $epos [0], $epos [1], $epos [2], $level ),$epos [3],$epos [4] ];
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! $player instanceof Player) {
			$this->alert ( $player, $this->get ( "only-in-game" ) );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-spawn" ) :
				$pos = $this->getSpawn ( $player );
				if ($pos != null) {
					$player->teleport ( $pos [0], $pos [1], $pos [2] );
					$this->message ( $player, $this->get ( "spawn-teleport-complete" ) );
				} else {
					$this->alert ( $player, $this->get ( "spawn-list-not-exist" ) );
				}
				break;
			case $this->get ( "commands-setspawn" ) :
				$this->config_Data ["spawns"] [] = $player->x . ":" . $player->y . ":" . $player->z . ":" . $player->yaw . ":" . $player->pitch . ":" . $player->getLevel ()->getFolderName ();
				$this->message ( $player, $this->get ( "setspawn-complete" ) );
				break;
			case $this->get ( "commands-spawnclear" ) :
				$this->config_Data ["spawns"] = [ ];
				$this->message ( $player, $this->get ( "spawnclear-complete" ) );
				break;
		}
		return true;
	}
	public function checkSpawn(Vector3 $tpos, $range = 3) {
		foreach ( $this->config_Data ["spawns"] as $index => $item ) {
			$epos = explode ( ":", $this->config_Data ["spawns"] [$index] );
			
			$dx = ( int ) abs ( $tpos->x - $epos [0] );
			$dy = ( int ) abs ( $tpos->y - $epos [1] );
			$dz = ( int ) abs ( $tpos->z - $epos [2] );
			
			if ($dx <= $range and $dy <= $range and $dz <= $range) return true;
		}
		return false;
	}
	public function onPlace(BlockPlaceEvent $event) {
		if ($event->getPlayer ()->isOp ()) return;
		if ($this->checkSpawn ( $event->getBlock (), 5 )) {
			$this->message ( $event->getPlayer (), $this->get ( "cannot-spawn-modify" ) );
			$event->setCancelled ();
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		if ($event->getPlayer ()->isOp ()) return;
		if ($this->checkSpawn ( $event->getBlock (), 5 )) {
			$this->message ( $event->getPlayer (), $this->get ( "cannot-spawn-modify" ) );
			$event->setCancelled ();
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (! $event->getAction ()) return;
		if ($event->getPlayer ()->isOp ()) return;
		if ($this->checkSpawn ( $event->getBlock (), 5 )) {
			$this->message ( $event->getPlayer (), $this->get ( "cannot-spawn-modify" ) );
			$event->setCancelled ();
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			if ($event->getEntity () instanceof Player) {
				if ($this->checkSpawn ( $event->getEntity (), 5 )) $event->setCancelled ();
			}
		}
	}
	public function onLaunch(ProjectileLaunchEvent $event) {
		$shooter = $event->getEntity ()->shootingEntity;
		if ($shooter instanceof Player) {
			if ($this->checkSpawn ( $shooter, 5 )) {
				$this->message ( $shooter, $this->get ( "cannot-spawn-pvp" ) );
				$event->setCancelled ();
			}
		}
	}
	public function onExplode(ExplosionPrimeEvent $event) {
		if ($this->checkSpawn ( $event->getEntity (), 5 )) $event->setCancelled ();
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>

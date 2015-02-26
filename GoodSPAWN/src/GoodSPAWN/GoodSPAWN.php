<?php

namespace GoodSPAWN;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
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

class GoodSPAWN extends PluginBase implements Listener {
	public $config, $config_Data;
	public $m_version = 1;
	public $spawn_queue = [ ];
	public $death_queue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ 
				"spawns" => [ ] ] );
		$this->config_Data = $this->config->getAll ();
		
		$this->registerCommand ( $this->get ( "commands-spawn" ), "goodspawn.spawn" );
		$this->registerCommand ( $this->get ( "commands-setspawn" ), "goodspawn.setspawn" );
		$this->registerCommand ( $this->get ( "commands-spawnclear" ), "goodspawn.spawnclear" );
		
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
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
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
	public function onJoin(PlayerJoinEvent $event) {
		if (! isset ( $this->spawn_queue [$event->getPlayer ()->getName ()] )) {
			$this->spawn_queue [$event->getPlayer ()->getName ()] = 1;
			$pos = $this->getSpawn ( $event->getPlayer () );
			if ($pos != null) $event->getPlayer ()->teleport ( $pos [0], $pos [1], $pos [2] );
		}
	}
	public function onRespawn(PlayerRespawnEvent $event) {
		if (isset ( $this->death_queue [$event->getPlayer ()->getName ()] )) {
			$pos = $this->getSpawn ( $event->getPlayer () );
			if ($pos != null) $event->setRespawnPosition ( $pos [0], $pos [1], $pos [2] );
			unset ( $this->death_queue [$event->getPlayer ()->getName ()] );
		}
	}
	public function onDeath(PlayerDeathEvent $event) {
		if (! isset ( $this->death_queue )) $this->death_queue [$event->getEntity ()->getName ()] = 1;
	}
	public function getSpawn(Player $player) {
		if (! isset ( $this->config_Data ["spawns"] ) or count ( $this->config_Data ["spawns"] ) == 0) return null;
		$rand = mt_rand ( 0, count ( $this->config_Data ["spawns"] ) - 1 );
		$epos = explode ( ":", $this->config_Data ["spawns"] [$rand] );
		$level = $this->getServer ()->getLevelByName ( $epos [5] );
		if (! $level instanceof Level) return null;
		return [ 
				new Position ( $epos [0], $epos [1], $epos [2], $level ),
				$epos [3],
				$epos [4] ];
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
					$this->alert($player, $this->get("spawn-list-not-exist"));
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
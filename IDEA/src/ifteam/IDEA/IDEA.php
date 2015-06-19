<?php

namespace ifteam\IDEA;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\level\format\FullChunk;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use pocketmine\utils\Config;

class IDEA extends PluginBase implements Listener {
	public $m_version = 1;
	public $synchro_queue = [ ];
	public $shift_queue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
		$this->registerCommand ( $this->get ( "idea" ), "idea.synchro" );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () ) != $this->get ( "idea" )) return;
		if (! isset ( $args [0] )) {
			$this->message ( $player, $this->get ( "help-synchro" ) );
			$this->message ( $player, $this->get ( "help-shift" ) );
			$this->message ( $player, $this->get ( "help-cancel" ) );
			return true;
		}
		switch ($args [0]) {
			case $this->get ( "synchro" ) :
				if (isset ( $this->shift_queue [$player->getName ()] )) unset ( $this->shift_queue [$player->getName ()] );
				if (! isset ( $this->synchro_queue [$player->getName ()] )) $this->synchro_queue [$player->getName ()] = 1;
				$this->message ( $player, $this->get ( "synchro-sequence-start" ) );
				$this->message ( $player, $this->get ( "please-make-dicision" ) );
				break;
			case $this->get ( "shift" ) :
				if (isset ( $this->synchro_queue [$player->getName ()] )) unset ( $this->synchro_queue [$player->getName ()] );
				if (! isset ( $this->shift_queue [$player->getName ()] )) $this->shift_queue [$player->getName ()] = 1;
				$this->message ( $player, $this->get ( "shift-sequence-start" ) );
				$this->message ( $player, $this->get ( "please-make-dicision" ) );
				break;
			case $this->get ( "cancel" ) :
				if (isset ( $this->shift_queue [$player->getName ()] )) unset ( $this->shift_queue [$player->getName ()] );
				if (isset ( $this->synchro_queue [$player->getName ()] )) unset ( $this->synchro_queue [$player->getName ()] );
				$this->message ( $player, $this->get ( "all-queue-cancelled" ) );
				break;
			default :
				$this->message ( $player, $this->get ( "help-synchro" ) );
				$this->message ( $player, $this->get ( "help-shift" ) );
				$this->message ( $player, $this->get ( "help-cancel" ) );
				break;
		}
		return true;
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (isset ( $this->synchro_queue [$event->getPlayer ()->getName ()] )) {
			if (isset ( $this->synchro_queue [$event->getPlayer ()->getName ()] )) unset ( $this->synchro_queue [$event->getPlayer ()->getName ()] );
			
			$event->setCancelled ();
			
			$player = $event->getPlayer ();
			$level = $player->getLevel ();
			
			$idea_level = $this->getServer ()->getLevelByName ( $level->getFolderName () . "_IDEA" );
			if (! $idea_level instanceof Level) {
				$player->sendMessage ( TextFormat::RED . $this->get ( "idea-doesnt-exist" ) );
				return;
			}
			$chunk = $idea_level->getChunk ( $event->getBlock ()->x >> 4, $event->getBlock ()->z >> 4, true );
			if (! $chunk instanceof FullChunk) {
				$player->sendMessage ( TextFormat::RED . $this->get ( "idea-is-breakdown" ) );
				return;
			}
			$c_chunk = clone $chunk;
			$c_chunk->setX ( $chunk->getX () );
			$c_chunk->setZ ( $chunk->getZ () );
			
			$level->setChunk ( $chunk->getX (), $chunk->getZ (), $c_chunk, true );
			$level->save ( true );
			
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "idea-instance-synchro-100" ) );
		} else if (isset ( $this->shift_queue [$event->getPlayer ()->getName ()] )) {
			if (isset ( $this->shift_queue [$event->getPlayer ()->getName ()] )) unset ( $event->getPlayer ()->shift_queue [$event->getPlayer ()->getName ()] );
			
			$event->setCancelled ();
			
			$player = $event->getPlayer ();
			$level = $player->getLevel ();
			
			$idea_level = $this->getServer ()->getLevelByName ( $level->getFolderName () . "_IDEA" );
			if (! $idea_level instanceof Level) {
				$player->sendMessage ( TextFormat::RED . $this->get ( "idea-doesnt-exist" ) );
				return;
			}
			$chunk = $level->getChunk ( $event->getBlock ()->x >> 4, $event->getBlock ()->z >> 4, true );
			if (! $chunk instanceof FullChunk) {
				$player->sendMessage ( TextFormat::RED . $this->get ( "idea-is-breakdown" ) );
				return;
			}
			$c_chunk = clone $chunk;
			$c_chunk->setX ( $chunk->getX () );
			$c_chunk->setZ ( $chunk->getZ () );
			
			$idea_level->setChunk ( $chunk->getX (), $chunk->getZ (), $c_chunk );
			$idea_level->save ( true );
			
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "idea-instance-synchro-100" ) );
		}
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